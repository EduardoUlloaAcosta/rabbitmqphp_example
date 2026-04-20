#!/bin/bash
HOST="100.64.20.43"
USER="bp558db"
PASS="123"
REMOTE="~/git/rabbitmqphp_example"

sshpass -p "$PASS" scp "/home/vboxuser/git/front_end_dev_errors.txt" "$USER@$HOST:$REMOTE"
