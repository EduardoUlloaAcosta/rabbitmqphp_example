#!/bin/bash

#Brian Patoilo / retrieve file 4/1.
#I am using a lot of ainesh wrote to keep the functionality of the deployment.
#The way it should work is that it gets changes from one cluster and then deploy.sh
#will push it to the machines afterwards.
source ~/deploy/vmconfigs.conf

PACKAGE_DIR=~/deploy/packages
CURRENT=~/deploy/current_ver
#remove dev folder because of bundling

mkdir -p $PACKAGE_DIR

#auto incrementing versions
NEXT_NUM=$(ls $PACKAGE_DIR | wc -l)
NEXT_NUM=$((NEXT_NUM+1))
VERSION="v$NEXT_NUM"
echo "version incremented, pulling dev cluster changes"

# 4/6 update: after talking to kehoe i am going to use
# scp instead of rsync now. Rsync copies all the files over (like scp)
# but it detects changes, only thing is that can't be zipped
# now the idea is to zip the directories on the remote machine
# then send it to the deploy machine to be zipped into one to be called a version

echo "pull frotned first"
ssh $FE_USER@$FE_IP "tar -czf /tmp/frontend.tar.gz -C $FE_PATH ."
scp $FE_USER@$FE_IP:/tmp/frontend.tar.gz /tmp/frontend.tar.gz
ssh $FE_USER@$FE_IP "rm /tmp/frontend.tar.gz"

echo "pull dmz second"
ssh $DMZ_USER@$DMZ_IP "tar -czf /tmp/dmz.tar.gz -C $DMZ_PATH ."
scp $DMZ_USER@$DMZ_IP:/tmp/dmz.tar.gz /tmp/dmz.tar.gz
ssh $DMZ_USER@$DMZ_IP "rm /tmp/dmz.tar.gz"

echo "pull backend last"
ssh $DB_USER@$DB_IP "tar -czf /tmp/db.tar.gz -C $DB_PATH ."
scp $DB_USER@$DB_IP:/tmp/db.tar.gz /tmp/db.tar.gz
ssh $DB_USER@$DB_IP "rm /tmp/db.tar.gz"

#now to get all three zips into one package it has to be copied to one area, and then zipped 
#to create one folder with the version number. then that temporary folder get's deleted so it doesn't leave unecessary
#storage taken up

mkdir -p /tmp/package
mv /tmp/frontend.tar.gz /tmp/package/
mv /tmp/dmz.tar.gz /tmp/package/
mv /tmp/db.tar.gz /tmp/package/
tar -czf $PACKAGE_DIR/$VERSION.tar.gz -C /tmp/package .
#clean up the loose files so they dont sit on the deploy machine
rm -rf /tmp/package
echo "$VERSION.tar.gz is packaged"
