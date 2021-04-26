symfony console doctrine:database:drop --force
echo "Removing user pictures..."
rm -vrf ${BASH_SOURCE%/*}/../public/uploads/models/*
rm -vrf ${BASH_SOURCE%/*}/../public/uploads/bikes/*
rm -vrf ${BASH_SOURCE%/*}/../public/uploads/users/*
symfony console doctrine:database:create
symfony console doctrine:schema:update --force
symfony console doctrine:fixtures:load --no-interaction 