# PHP Stripe + Postgres (Docker + Render)

## Requisiti
- Docker
- Composer

## Setup in locale
```bash
docker build -t php-stripe-app .
docker run -p 8080:80 php-stripe-app
