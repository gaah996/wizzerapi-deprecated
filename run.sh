echo Uploading Application container 
docker-compose up -d

echo Install dependencies
docker exec -it app-v1 composer install

echo Generate key
docker exec -it app-v1 php artisan key:generate

echo Make migrations
docker exec -it app-v1 php artisan migrate:fresh

echo Install passport
docker exec -it app-v1 php artisan passport:install