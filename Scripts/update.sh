#!/bin/bash
VER="";
while getopts ":v:" opt; do
    case $opt in
        v) VER="$OPTARG"
        ;;
        \?) echo "Invalid option -$OPTARG" >&2
        ;;
    esac
done

#cd to a level above root dir, we're virtually executing in [root]/public
cd ..

if [[ -n $VER ]];
then

    echo "Backup current version"
    tar -czf BikeLog_backup_before_$VER.tar.gz *

    echo "Downloading the desired release"
    wget https://github.com/gztproject/BikeLog/archive/refs/tags/$VER.tar.gz
    echo "Extracting release"
    tar -xf $VER.tar.gz
    rm $VER.tar.gz
    #We've got the new release in dir BikeLog-$VER

    echo "Copy over current version backup"
    cp -v BikeLog_backup_before_$VER.tar.gz BikeLog-$VER/
    echo "Copy over config"
    cp -v .env BikeLog-$VER/.env
    cp -v .env.local BikeLog-$VER/.env.local
    echo "Copy over logs"
    mkdir BikeLog-$VER/var
    mkdir BikeLog-$VER/var/log
    cp -rv var/log/* BikeLog-$VER/var/log/
    echo "Copy over user stuff..."
    mkdir BikeLog-$VER/public/uploads
    cp -rv public/uploads/* BikeLog-$VER/public/uploads/

    echo "Remove current version"
    GLOBIGNORE=BikeLog-*
    rm -rf *
    unset GLOBIGNORE

    echo "Copy over the new version"
    cp -r BikeLog-$VER/. .
    rm -rf BikeLog-$VER   

else
    echo "No version provided via the -v argument"
    exit 1
fi

#Instal dependencies 
#composer self-update
composer clear-cache
composer update --no-dev

yarn install

#Build new .js and .css
yarn encore prod

#Migrate DB if necessary
php bin/console doctrine:migrations:migrate -n

#clear the cache
php bin/console cache:clear

exit 0