.ONESHELL:

BUILDDIR := $(subst /,\,$(CURDIR))
TOPDIR := $(BUILDDIR)\..\..

TARGETDIR = $(TOPDIR)\go\src\zabbix\cmd\zabbix_agent2
TARGET=$(TARGETDIR)\zabbix_agent2.exe

CC = gcc

OBJS = \
	threads.o \
	str.o \
	misc.o \
	fatal.o \
	iprange.o \
	md5.o \
	sysinfo.o \
	vector.o \
	zbxregexp.o \
	logfiles.o \
	file.o \
	alias.o \
	algodefs.o \
	sysinfo_system.o \

CFLAGS := $(CFLAGS) -O2 -g -DUNICODE -DHAVE_STDINT_H=1 -I$(TOPDIR)\include -I$(TOPDIR)\build\win32\include
GOPATH := $(GOPATH);$(TOPDIR)\go
CGO_CFLAGS := $(CGO_CFLAGS) $(CFLAGS) 

ifeq ("$(ARCH)", "x86")
	CFLAGS := $(CFLAGS) -m32
	CGO_CFLAGS := $(CGO_CFLAGS) -m32
	GOARCH := 386
else
	GOARCH := amd64
endif

ifneq ("$(OPENSSL)", "")
	CFLAGS := $(CFLAGS) -DHAVE_OPENSSL=1 -DHAVE_OPENSSL_WITH_PSK=1
	ifneq (,$(wildcard $(OPENSSL)))
		CFLAGS := $(CFLAGS) -I$(OPENSSL)\include
		LDFLAGS := $(LDFLAGS) -L$(OPENSSL)\lib
		CGO_CFLAGS := $(CGO_CFLAGS) -I$(OPENSSL)\include
		CGO_LDFLAGS := $(CGO_LDFLAGS) -L$(OPENSSL)\lib
	endif
endif

ifneq ("$(PCRE)", "")
	CFLAGS := $(CFLAGS) -I$(PCRE)\include
	LDFLAGS := $(LDFLAGS) -L$(PCRE)\lib
	CGO_CFLAGS := $(CGO_CFLAGS) -I$(PCRE)\include
	CGO_LDFLAGS := $(CGO_LDFLAGS) -L$(PCRE)\lib
endif

#CGO_LDFLAGS := $(CGO_LDFLAGS) -Wl,--start-group $(patsubst %,$(BUILDDIR)\\%,$(OBJS)) -lpcre -lDbghelp -lpsapi -Wl,--end-group

#CGO_LDFLAGS := $(CGO_LDFLAGS) -Wl,--start-group -lpcre -lDbghelp -lpsapi -Wl,--end-group


all: $(TARGET)

misc.o: $(TOPDIR)\src\libs\zbxcommon\misc.c
	$(CC) $(CFLAGS) -c $^ -o $@

iprange.o: $(TOPDIR)\src\libs\zbxcommon\iprange.c
	$(CC) $(CFLAGS) -c $^ -o $@

str.o: $(TOPDIR)\src\libs\zbxcommon\str.c
	$(CC) $(CFLAGS) -c $^ -o $@

file.o: $(TOPDIR)\src\libs\zbxcommon\file.c
	$(CC) $(CFLAGS) -c $^ -o $@

alias.o: $(TOPDIR)\src\libs\zbxcommon\alias.c
	$(CC) $(CFLAGS) -c $^ -o $@

fatal.o: $(TOPDIR)\src\libs\zbxwin32\fatal.c
	$(CC) $(CFLAGS) -c $^ -o $@

threads.o: $(TOPDIR)\src\libs\zbxsys\threads.c
	$(CC) $(CFLAGS) -c $^ -o $@

md5.o: $(TOPDIR)\src\libs\zbxcrypto\md5.c
	$(CC) $(CFLAGS) -c $^ -o $@

sysinfo.o: $(TOPDIR)\src\libs\zbxsysinfo\sysinfo.c
	$(CC) $(CFLAGS) -DWITH_COMMON_METRICS -c $^ -o $@

sysinfo_system.o: $(TOPDIR)\src\libs\zbxsysinfo\common\system.c
	$(CC) $(CFLAGS) -c $^ -o $@

sysinfo_net.o: $(TOPDIR)\src\libs\zbxsysinfo\common\net.c
	$(CC) $(CFLAGS) -c $^ -o $@

vector.o: $(TOPDIR)\src\libs\zbxalgo\vector.c
	$(CC) $(CFLAGS) -c $^ -o $@

algodefs.o: $(TOPDIR)\src\libs\zbxalgo\algodefs.c
	$(CC) $(CFLAGS) -c $^ -o $@

zbxregexp.o: $(TOPDIR)\src\libs\zbxregexp\zbxregexp.c
	$(CC) $(CFLAGS) -DPCRE_STATIC -c $^ -o $@

logfiles.o: $(TOPDIR)\src\zabbix_agent\logfiles\logfiles.c
	$(CC) $(CFLAGS) -c $^ -o $@


.FORCE:
$(TARGET): $(OBJS) .FORCE
	cd $(TARGETDIR)
	set CGO_CFLAGS=$(CGO_CFLAGS)
	set CGO_LDFLAGS=$(CGO_LDFLAGS)
	set GOARCH=$(GOARCH)
	set CGO_ENABLED=1
	go build

clean:
	del "$(TARGET)" $(OBJS) 2>NUL
