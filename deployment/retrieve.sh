#!/bin/bash

#Brian Patoilo / retrieve file 4/1.
#I am using a lot of ainesh wrote to keep the functionality of the deployment.
#The way it should work is that it gets changes from one cluster and then deploy.sh
#will push it to the machines afterwards.
source ~/deploy/vmconfigs.conf

PACKAGE_DIR=~/deploy/packages
CURRENT=~/deploy/current_ver
DEV_DIR=~/deploy/dev #wrote to make easier

mkdir -p $PACKAGE_DIR

#auto incrementing
NEXT_NUM=$(ls $PACKAGE_DIR | wc -l)
NEXT_NUM=$((NEXT_NUM+1))
VERSION="v$NEXT_NUM"
echo "version incremented, pulling dev cluster changes"

echo "pull frotned first"
rsync -avz --chmod=755 $FE_USER@$FE_IP:$FE_PATH/ $DEV_DIR/frontend/

echo "pull dmz second"
rsync -avz --chmod=755 $DMZ_USER@$DMZ_IP:$DMZ_PATH/ $DEV_DIR/dmz/

echo "pull backend last"
rsync -avz --chmod=755 $DB_USER@$DB_IP:$DB_PATH/ $DEV_DIR/db/

#I added the chmod command because there were errors with read permissions from the frontend
#so I did it for all to play it safe

CHANGES=false #flag made to check for differences

if [ -f "$CURRENT" ]; then
    LAST_VER=$(cat $CURRENT)
    LAST_PKG=$PACKAGE_DIR/$LAST_VER
    # frontend
    if [ -d "$LAST_PKG/frontend" ]; then
        diff -rq $DEV_DIR/frontend/ $LAST_PKG/frontend/ > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "frontend has changes"
            CHANGES=true
        fi
    else
        echo "frontend files changed"
        CHANGES=true
    fi
    #dmz
    if [ -d "$LAST_PKG/dmz" ]; then
        diff -rq $DEV_DIR/dmz/ $LAST_PKG/dmz/ > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "dmz has changes"
            CHANGES=true
        fi
    else
        echo "dmz files changed"
        CHANGES=true
    fi
    #backend
    if [ -d "$LAST_PKG/db" ]; then
        diff -rq $DEV_DIR/db/ $LAST_PKG/db/ > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "db has changes"
            CHANGES=true
        fi
    else
        echo "db files changed"
        CHANGES=true
    fi
else
    echo "no previous (except I know there are so this will never get seen)"
    CHANGES=true
fi

if [ "$CHANGES" = true ]; then
    echo "packaging new files yerttt"
    PKG_DIR=$PACKAGE_DIR/$VERSION
    mkdir -p $PKG_DIR/frontend $PKG_DIR/dmz $PKG_DIR/db

    cp -r $DEV_DIR/frontend/. $PKG_DIR/frontend/
    cp -r $DEV_DIR/dmz/. $PKG_DIR/dmz/
    cp -r $DEV_DIR/db/. $PKG_DIR/db/
    echo "$VERSION is made yertt"
else
    echo "no changes or something went wrong"
fi

