#! /bin/bash

ROOT_DIR=$(cd "$(dirname "$0")";pwd)
echo $ROOT_DIR

function read_dir_and_insert_index(){
    for file in `ls $1`
    do
        if [ -d $1"/"$file ]
        then
            echo $1"/"$file
            cp $ROOT_DIR/index.php $1"/"$file
            read_dir_and_insert_index $1"/"$file
        fi
    done
}

read_dir_and_insert_index $ROOT_DIR

rm -fr $ROOT_DIR/.git
rm -fr $ROOT_DIR/vendor/datacue/client/.git
rm -fr $ROOT_DIR/.DS_Store
rm -fr $ROOT_DIR/config.xml
rm $ROOT_DIR/release.sh
