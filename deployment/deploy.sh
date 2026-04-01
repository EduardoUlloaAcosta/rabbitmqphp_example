#!/bin/bash
source ~/deploy/vmconfigs.conf

PACKAGE_DIR=~/deploy/packages
CURRENT=~/deploy/current_ver
BAD=~/deploy/bad_ver

mkdir -p $PACKAGE_DIR

ACTION=$1
VERSION=$2

if [ "$ACTION" == "deploy" ]; then
    if [ -z "$VERSION" ]; then
        echo "usage: ./deploy.sh deploy <version>"
        exit 1
    fi

    PKG_DIR=$PACKAGE_DIR/$VERSION
    mkdir -p $PKG_DIR/frontend $PKG_DIR/dmz $PKG_DIR/db

    echo "we packing up ver. $VERSION"
    cp -r ~/deploy/dev/frontend/. $PKG_DIR/frontend/
    cp -r ~/deploy/dev/dmz/. $PKG_DIR/dmz/
    cp -r ~/deploy/dev/db/. $PKG_DIR/db/

    echo "we deploying $VERSION now!"

if [[ "$(ls -A $PKG_DIR/frontend/)" ]]; then
    echo "hold up we deployin' to frontend O_O"
    rsync -avz $PKG_DIR/frontend/ $FE_USER@$FE_IP:$FE_PATH/
else
    echo "ok frontend had nothing lmao"
fi

if [[ "$(ls -A $PKG_DIR/dmz/)" ]]; then
    echo "ok now we deployin' to dmz"
    rsync -avz $PKG_DIR/dmz/ $DMZ_USER@$DMZ_IP:$DMZ_PATH/
else
    echo "dmz had nothin'"
fi

if [[ "$(ls -A $PKG_DIR/db/)" ]]; then
    echo "last but not least we deployin' to dat db fr"
    rsync -avz $PKG_DIR/db/ $DB_USER@$DB_IP:$DB_PATH/
else
    echo "db got no money (no updates)"
fi

echo $VERSION > $CURRENT
echo "mk $VERSION has been deployed"

elif [ "$ACTION" == "bad" ]; then
    if [ -z "$VERSION" ]; then
    echo "usage ./deploy.sh bad <version>"
    exit 1
    fi

    echo $VERSION >> $BAD
    echo "$VERSION has been marked as bad :("

elif [ "$ACTION" == "rollback" ]; then
    if [ -z "$VERSION" ]; then
    echo "usage ./deploy.sh rollback <version>"
    exit 1
    fi

    PKG_DIR=$PACKAGE_DIR/$VERSION
    if [ ! -d "$PKG_DIR" ]; then
        echo "version $VERSION not found!"
        exit 1
    fi

    echo "we are rolling back to $VERSION"
    if [[ "$(ls -A $PKG_DIR/frontend/)" ]]; then
        rsync -avz $PKG_DIR/frontend/ $FE_USER@$FE_IP:$FE_PATH/
    else
        echo "frontend nothing to rollback"
    fi

    if [[ "$(ls -A $PKG_DIR/dmz/)" ]]; then
        rsync -avz $PKG_DIR/dmz/ $DMZ_USER@$DMZ_IP:$DMZ_PATH/
    else
        echo "dmz had nothin'"
    fi

    if [[ "$(ls -A $PKG_DIR/db/)" ]]; then
        rsync -avz $PKG_DIR/db/ $DB_USER@$DB_IP:$DB_PATH/
    else
        echo "db had no money (nothing to rollback)"
    fi

    echo $VERSION > $CURRENT
    echo "we have just rolled back to $VERSION"

else
    echo "usage: ./deploy.sh [deploy|bad|rollback] <version>"
fi
