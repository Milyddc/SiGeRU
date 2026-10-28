#!/bin/sh
cd "$(dirname "$0")/.." || exit 1

COMPOSE="docker compose -f docker-compose.test.yml"
SERVICIOS="${1:-usuarios gestion recoleccion}"
RESULTADO=0

$COMPOSE up -d --wait db-test || exit 1

for s in $SERVICIOS; do
  echo ""
  echo "=============== Tests de api-$s ==============="
  $COMPOSE run --rm --build "test-$s" || RESULTADO=1
done

$COMPOSE down -v
exit $RESULTADO
