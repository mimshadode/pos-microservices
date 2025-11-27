.PHONY: help up down restart logs clean install test

help:
	@echo "POS Microservices - Available Commands:"
	@echo "  make up         - Start all services"
	@echo "  make down       - Stop all services"
	@echo "  make restart    - Restart all services"
	@echo "  make logs       - Show logs"
	@echo "  make clean      - Clean all data"
	@echo "  make install    - Install all dependencies"
	@echo "  make test       - Run tests"

up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

logs:
	docker-compose logs -f

clean:
	docker-compose down -v
	rm -rf */vendor */node_modules

install:
	@echo "Installing dependencies for all services..."
	cd gateway && composer install
	cd auth-service && composer install
	cd product-service && composer install
	cd order-service && composer install
	cd payment-service && composer install
	cd reporting-service && composer install

test:
	@echo "Running tests for all services..."
	cd gateway && php artisan test
	cd auth-service && php artisan test
	cd product-service && php artisan test
	cd order-service && php artisan test
	cd payment-service && php artisan test
	cd reporting-service && php artisan test
