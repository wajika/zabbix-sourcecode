/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package zbxcmd

import (
	"bytes"
	"fmt"
	"os/exec"
	"strings"
	"time"
	"unsafe"
	"zabbix/pkg/log"

	"golang.org/x/sys/windows"
)

type process struct {
	Pid    int
	Handle uintptr
}

func Execute(s string, timeout time.Duration) (string, error) {
	cmd := exec.Command("cmd", "/C", s)

	var b bytes.Buffer
	cmd.Stdout = &b
	cmd.Stderr = &b

	job, err := windows.CreateJobObject(nil, nil)
	if err != nil {
		return "", err
	}
	defer windows.CloseHandle(job)

	info := windows.JOBOBJECT_EXTENDED_LIMIT_INFORMATION{
		BasicLimitInformation: windows.JOBOBJECT_BASIC_LIMIT_INFORMATION{
			LimitFlags: windows.JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE,
		},
	}

	if _, err := windows.SetInformationJobObject(job, windows.JobObjectExtendedLimitInformation,
		uintptr(unsafe.Pointer(&info)), uint32(unsafe.Sizeof(info))); err != nil {
		return "", err
	}

	err = cmd.Start()

	if err != nil {
		return "", fmt.Errorf("Cannot execute command: %s", err)
	}

	if err = windows.AssignProcessToJobObject(job, windows.Handle((*process)(unsafe.Pointer(cmd.Process)).Handle)); err != nil {
		return "", err
	}

	t := time.AfterFunc(timeout, func() {
		if err = windows.TerminateJobObject(job, 0); err != nil {
			log.Warningf("failed to kill [%s]: %s", s, err)
		}
	})

	_ = cmd.Wait()

	if !t.Stop() {
		return "", fmt.Errorf("Timeout while executing a shell script.")
	}

	if maxExecuteOutputLenB <= len(b.String()) {
		return "", fmt.Errorf("Command output exceeded limit of %d KB", maxExecuteOutputLenB/1024)
	}

	return strings.TrimRight(b.String(), " \t\r\n"), nil
}

func ExecuteBackground(s string) error {
	cmd := exec.Command("cmd", "/C", s)

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("Cannot execute command: %s", err)
	}

	go func() { _ = cmd.Wait() }()

	return nil
}
