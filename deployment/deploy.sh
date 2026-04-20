#!/bin/bash
#ainesh - 3/31/2026
#this file handles deployment, rollback, and marking versions as bad.
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

    PKG_TAR=$PACKAGE_DIR/$VERSION.tar.gz

    if [ ! -f "$PKG_TAR" ]; then
        echo "version $VERSION not found </3"
        exit 1
    fi
#ainesh 4/6/26 - updating file to fit our new deployment by extracting thru ssh

    #extracting version tarball for all the respective tar files
    EXTRACT_DIR=/tmp/deploy_$VERSION
    mkdir -p $EXTRACT_DIR
    tar -xzf $PKG_TAR -C $EXTRACT_DIR

    echo "we deploying $VERSION now!"

#ssh'ing into machines and then extractin em there

    echo "hold up we deployin' to frontend O_O"
    scp $EXTRACT_DIR/frontend.tar.gz $FE_USER@$FE_IP:/tmp/
    ssh $FE_USER@$FE_IP "tar -xzf /tmp/frontend.tar.gz -C $FE_PATH"

    echo "ok now we deployin' to dmz"
    scp $EXTRACT_DIR/dmz.tar.gz $DMZ_USER@$DMZ_IP:/tmp/
    ssh $DMZ_USER@$DMZ_IP "tar -xzf /tmp/dmz.tar.gz -C $DMZ_PATH"


    echo "last but not least we deployin' to dat db fr"
    scp $EXTRACT_DIR/db.tar.gz $DB_USER@$DB_IP:/tmp/
    ssh $DB_USER@$DB_IP "tar -xzf /tmp/db.tar.gz -C $DB_PATH"

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

#similar to deploy
    PKG_TAR=$PACKAGE_DIR/$VERSION.tar.gz

     if [ ! -f "$PKG_TAR" ]; then
        echo "version $VERSION not found lols"
        exit 1
    fi

     EXTRACT_DIR=/tmp/deploy_$VERSION
        mkdir -p $EXTRACT_DIR
        tar -xzf $PKG_TAR -C $EXTRACT_DIR

    echo "we are rolling back to $VERSION"

    echo "fixin frontned"
    scp $EXTRACT_DIR/frontend.tar.gz $FE_USER@$FE_IP:/tmp/
    ssh $FE_USER@$FE_IP "tar -xzf /tmp/frontend.tar.gz -C $FE_PATH"

    echo "fixin dat dmz"
    scp $EXTRACT_DIR/dmz.tar.gz $DMZ_USER@$DMZ_IP:/tmp/
    ssh $DMZ_USER@$DMZ_IP "tar -xzf /tmp/dmz.tar.gz -C $DMZ_PATH"

    echo "time to fix db."
    scp $EXTRACT_DIR/db.tar.gz $DB_USER@$DB_IP:/tmp/
    ssh $DB_USER@$DB_IP "tar -xzf /tmp/db.tar.gz -C $DB_PATH"

    echo $VERSION > $CURRENT
    echo "we have just rolled back to $VERSION"

else
    echo "usage: ./deploy.sh [deploy|bad|rollback] <version>"
fi

