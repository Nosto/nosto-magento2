#!/bin/bash
set -e

if [ "$1" != "" ]; then
  exec "$@"
else
  exec supervisord -c /etc/supervisord.conf
fi
