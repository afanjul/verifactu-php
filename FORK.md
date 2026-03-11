# Fork mantenido por afanjul

Este repositorio mantiene un fork de `josemmo/Verifactu-PHP` pensado para ser consumido como reemplazo compatible del paquete `josemmo/verifactu-php`.

## Uso desde Composer

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/afanjul/verifactu-php"
    }
  ],
  "require": {
    "josemmo/verifactu-php": "dev-develop"
  }
}
```

## Estructura de ramas

- `main`
  - espejo limpio del upstream
- `develop`
  - rama de trabajo del fork

## Sincronización con upstream

```bash
git checkout main
git fetch upstream
git merge upstream/main
git push origin main

git checkout develop
git merge main
git push origin develop
```

## Nota para consumidores

La documentación de integración de la librería está en `README.md`, `docs/` y `llms.txt`.
Este archivo solo cubre detalles de mantenimiento del fork.
