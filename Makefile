.PHONY: up up-all down migrate seed-woocommerce test-order simulate-order test lint stan fresh logs

up:
	docker compose up -d --build --wait

up-all:
	docker compose --profile woocommerce up -d --build --wait

down:
	docker compose --profile woocommerce --profile rabbitmq down

migrate:
	docker compose exec -T api php artisan migrate --force

seed-woocommerce:
	docker compose --profile woocommerce up -d --wait
	docker compose exec -T wpcli sh /scripts/setup.sh

test-order:
	docker compose exec -T wpcli sh /scripts/create-test-order.sh

simulate-order:
	docker compose exec -T api php artisan woocommerce:simulate-order $(ARGS)

test:
	docker compose exec -T -e APP_ENV=testing -e SESSION_DRIVER=array -e CACHE_STORE=array -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: api ./vendor/bin/pest

lint:
	docker compose exec -T api ./vendor/bin/pint --test

stan:
	docker compose exec -T api ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M

fresh:
	@test "$(CONFIRM)" = "fresh" || { echo "Destrutivo: use make fresh CONFIRM=fresh"; exit 1; }
	docker compose exec -T api php artisan migrate:fresh --force

logs:
	docker compose --profile woocommerce logs -f --tail=100
