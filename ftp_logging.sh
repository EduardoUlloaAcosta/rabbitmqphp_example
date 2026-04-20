#!/bin/bash
HOST="100.64.20.43"
USER="bp558db"
PASS="123"
LOCAL="/var/log/evry_error.log"
REMOTE="~/git/rabbitmqphp_example"


sshpass -p "$PASS" scp "$LOCAL" "$USER@$HOST:$REMOTE"


