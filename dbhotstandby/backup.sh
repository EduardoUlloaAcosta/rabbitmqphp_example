#!/bin/bash
# created by ainesh 4/13/26 - this will ping main db machine and take over by changing config files on frontend and dmz if ping fails repeatedly

LIMIT=1
IP1="100.84.167.48"
IP2="100.79.180.77"
FAIL_MARK="/tmp/fail"

#i did this as a way to check if the file exists. if it does then then it doesn't run the script
if [ -f "$FAIL_MARK" ]; then
    exit 0
fi

#pinging and counting fails
nc -zv -w 3 $IP1 5672
NC=$?
echo "pinged at $(date)"
COUNTER=$(cat /tmp/fail_counter 2>/dev/null || echo 0)
if [ $NC -ne 0 ]; then
    COUNTER=$((COUNTER + 1))
    echo $COUNTER > /tmp/fail_counter
fi

if [ $COUNTER -ge $LIMIT ]; then
    touch $FAIL_MARK

#ssh'ing into machines and update IPs
ssh vboxuser@100.100.135.97 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/frontend/FEconfig.php"
ssh vboxuser@100.87.44.64 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/frontend/FEconfig.php" #added 4/29 by ainesh - changing backup frontend IP
ssh vboxuser@100.80.94.55 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/worker/dmzRabbitMQ.ini"
ssh vboxuser@100.117.9.105 "sudo sed -i 's/$IP1/$IP2/g' /home/vboxuser/git/rabbitmqphp_example/worker/dmzRabbitMQ.ini"


sudo mysql -e "set global read_only = 0;"

echo "backup took over at $(date) o_o"

fi

