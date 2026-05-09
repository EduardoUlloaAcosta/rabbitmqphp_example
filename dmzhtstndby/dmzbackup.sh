#!bin/bash
# created by ainesh 4/17/2026 - pings  primary dmz. starts dmzworker if ping fails. pulled logic from how i wrote backup.sh

LIMIT=3
IP1="100.117.9.105"
FAIL_MARK="/tmp/fail"
COUNTER="/tmp/fail_counter"

#same thing as backup.sh. uses as a check to see if already ran
if [ -f "$FAIL_MARK" ]; then
    exit 0
fi

#pinging main dmz
ping -c 1 $IP1
PING=$?
COUNT=$(cat $COUNTER 2>/dev/null || echo 0)

if [ $PING -ne 0 ]; then
    COUNT=$((COUNT + 1))
    echo $COUNT > $COUNTER
else
    echo 0 > $COUNTER
    exit 0
fi

if [ $COUNT -ge $LIMIT ]; then
    touch $FAIL_MARK
    sudo systemctl start dmzworker.service
    echo "dmz backup took over at $(date) woahhh"
fi
