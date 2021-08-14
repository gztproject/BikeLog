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

#cd to root dir, we're virtually executing in [root]/public
cd ..

# Pull master branch
if [[ -n $VER ]];
then
    git pull origin master
    git checkout tags/$VER
else
    echo "No version provided via the -v argument"
    exit 1
fi

#Instal dependencies 
composer install --no-dev --optimize-autoloader
composer update
yarn install

#Build new .js and .css
yarn encore prod

#Migrate DB if necessary
php bin/console doctrine:migrations:migrate -n

#clear the cache
php bin/console cache:clear

exit 0