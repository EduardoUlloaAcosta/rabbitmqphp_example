#!/bin/bash
# created by ainesh 4/13/26 - this will ping main db machine and take over by changing config files on frontend and dmz if ping fails repeatedly

LIMIT=3
IP1="100.84.167.48"
IP2="100.79.180.77"
FAIL_MARK="/tmp/fail"

#i did this as a way to check if the file exists. if it does then then it doesn't run the script
if [ -f "$FAIL_MARK" ]; then
    exit 0
fi

#pinging and counting fails
nc -zv $IP1 5672
NC=$?
COUNTER=$(cat /tmp/fail_counter 2>/dev/null || echo 0)
if [ $NC -ne 0 ]; then
    COUNTER=$((COUNTER + 1))
    echo $COUNTER > /tmp/fail_counter
fi

if [ $COUNTER -ge $LIMIT ]; then
    touch $FAIL_MARK

#ssh'ing into machines and update IPs
ssh vboxuser@100.100.135.97 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/frontend/FEconfig.php"
ssh vboxuser@100.117.9.105 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/worker/dmzRabbitMQ.ini"

echo "backup took over at $(date) o_o"
fi


