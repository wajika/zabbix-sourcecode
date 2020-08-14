/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package agent

import (
	"bytes"
	"fmt"
	"strings"
	"time"
	"unicode"
	"os"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxcmd"
)

type parameterInfo struct {
	cmd      string
	flexible bool
}

// Plugin -
type UserParameterPlugin struct {
	plugin.Base
	parameters           map[string]*parameterInfo
	unsafeUserParameters int
	scriptLocations      []string
}

var userParameter UserParameterPlugin

func (p *UserParameterPlugin) cmd(key string, params []string) (string, error) {
	var b bytes.Buffer

	parameter := p.parameters[key]
	s := parameter.cmd

	if parameter.flexible {
		var n int

		for i := 0; i < len(params); i++ {
			n += len(params[i])
		}

		b.Grow(len(s) + n)

		for i := strings.IndexByte(s, '$'); i != -1; i = strings.IndexByte(s, '$') {
			if len(s) > i+1 && s[i+1] >= '1' && s[i+1] <= '9' && int(s[i+1]-'0') <= len(params) {
				param := params[s[i+1]-'0'-1]
				if p.unsafeUserParameters == 0 {
					if j := strings.IndexAny(param, "\\'\"`*?[]{}~$!&;()<>|#@\n"); j != -1 {
						if unicode.IsPrint(rune(param[j])) {
							return "", fmt.Errorf("Character \"%c\" is not allowed", param[j])
						}

						return "", fmt.Errorf("Character 0x%02x is not allowed", param[j])
					}
				}

				b.WriteString(s[:i])
				b.WriteString(param)
				s = s[i+2:]
			} else {
				b.WriteString(s[:i+1])
				s = s[i+1:]
			}
		}

		if len(s) != 0 {
			b.WriteString(s)
		}

		s = b.String()
	} else if len(params) > 0 {
		return "", fmt.Errorf("Parameters are not allowed.")
	}

	return s, nil
}

// Export -
func (p *UserParameterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	s, err := p.cmd(key, params)
	if err != nil {
		return nil, err
	}

	full_cmd := ""

	for i := 0; i < len(p.scriptLocations) + 1; i++ {
		if i < len(p.scriptLocations) {
			if p.scriptLocations[i] == "" {
				continue
			}

			full_cmd = p.scriptLocations[i] + "/" + s
			base_cmd := ""

			for j := 0; j < len(full_cmd) && full_cmd[j] != ' ' && full_cmd[j] != '\t'; j++ {
				base_cmd += string(full_cmd[j])
			}

			if f, err := os.Stat(base_cmd); err != nil {
				p.Debugf("cannot stat '%s' (%s)", base_cmd, err)
			} else {
				if f.IsDir() {
					p.Debugf("'%s' is not a regular file", base_cmd)
				} else {
					break
				}
			}
		} else {
			full_cmd = s;
		}
	}

	p.Debugf("executing command:'%s'", s)

	stdoutStderr, err := zbxcmd.Execute(full_cmd, time.Second*time.Duration(Options.Timeout))
	if err != nil {
		return nil, err
	}

	p.Debugf("command:'%s' length:%d output:'%.20s'", s, len(stdoutStderr), stdoutStderr)

	return stdoutStderr, nil
}

func InitUserParameterPlugin(userParameterConfig []string, unsafeUserParameters int, scriptLocations []string) error {
	userParameter.parameters = make(map[string]*parameterInfo)
	userParameter.unsafeUserParameters = unsafeUserParameters
	userParameter.scriptLocations = scriptLocations

	for i := 0; i < len(userParameterConfig); i++ {
		s := strings.SplitN(userParameterConfig[i], ",", 2)

		if len(s) != 2 {
			return fmt.Errorf("cannot add user parameter \"%s\": not comma-separated", userParameterConfig[i])
		}

		key, p, err := itemutil.ParseKey(s[0])
		if err != nil {
			return fmt.Errorf("cannot add user parameter \"%s\": %s", userParameterConfig[i], err)
		}

		if acc, _ := plugin.Get(key); acc != nil {
			return fmt.Errorf(`cannot register user parameter "%s": key already used`, userParameterConfig[i])
		}

		if len(strings.TrimSpace(s[1])) == 0 {
			return fmt.Errorf("cannot add user parameter \"%s\": command is missing", userParameterConfig[i])
		}

		parameter := &parameterInfo{cmd: s[1]}

		if len(p) == 1 && p[0] == "*" {
			parameter.flexible = true
		} else if len(p) != 0 {
			return fmt.Errorf("cannot add user parameter \"%s\": syntax error", userParameterConfig[i])
		}

		userParameter.parameters[key] = parameter
		plugin.RegisterMetrics(&userParameter, "UserParameter", key, fmt.Sprintf("User parameter: %s.", s[1]))
	}

	return nil
}
