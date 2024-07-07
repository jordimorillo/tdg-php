### README.md

# TDG-PHP

TDG-PHP es una herramienta para generar y probar código PHP basado en tests proporcionados. Utiliza un servicio de generación de código y ejecuta las pruebas para asegurar que el código generado cumple con los requisitos especificados en los tests.

## Instalación

Para instalar TDG-PHP en tu proyecto, ejecuta el siguiente comando:

```bash
composer require jordimorillo/tdg-php
```

## Uso

Una vez instalado, puedes utilizar el comando `tdg-php` para generar y probar código basado en un archivo de test PHP. El comando se ejecuta de la siguiente manera:

```bash
./vendor/bin/tdg-php ruta/al/archivo/test.php
```

### Parámetros

- `ruta/al/archivo/test.php`: La ruta al archivo de test PHP que se utilizará para generar el código.

### Ejemplo de uso

```bash
./vendor/bin/tdg-php tests/ExampleTest.php
```

## Configuración

Para que la herramienta funcione correctamente, es necesario configurar ciertas variables de entorno. A continuación se describen las variables necesarias y sus propósitos:

- `CONTAINER_PROJECT_ROOT`: La ruta raíz del proyecto dentro del contenedor Docker.
- `PHPUNIT_XML_PATH`: La ruta al archivo de configuración de PHPUnit.
- `BASE_NAMESPACE`: El namespace base del proyecto.
- `TESTS_BASE_NAMESPACE`: El namespace base para los tests.
- `OPENAI_API_KEY`: La clave API para el servicio de generación de código.
- `MAX_ATTEMPTS`: El número máximo de intentos para generar un código válido.
- `PHP_DOCKER_CONTAINER_NAME`: El nombre del contenedor Docker que ejecutará las pruebas.
- `PERMANENT_ATTACHMENTS`: Archivos adicionales que se adjuntarán siempre en la generación de código.

### Ejemplo de archivo `.env`

```env
CONTAINER_PROJECT_ROOT=/path/to/project
PHPUNIT_XML_PATH=/path/to/phpunit.xml
BASE_NAMESPACE=App
TESTS_BASE_NAMESPACE=Tests
OPENAI_API_KEY=your_openai_api_key
MAX_ATTEMPTS=5
PHP_DOCKER_CONTAINER_NAME=php-container
PERMANENT_ATTACHMENTS=src/Helper.php,src/Utils.php
```

## Desarrollo

### Requisitos

- PHP 7.4 o superior
- Composer
- Docker
- Extensión de PHP para cURL

### Ejecutar Tests

Para ejecutar los tests, utiliza el siguiente comando:

```bash
composer test
```

### Estructura del Proyecto

El proyecto sigue una arquitectura hexagonal. A continuación se describe brevemente cada uno de los componentes:

- `src/Application/Service/TestService.php`: Servicio principal que orquesta la generación y prueba de código.
- `src/Domain/`: Contiene la lógica de dominio del proyecto.
- `src/Infrastructure/Persistence/CodeRepository.php`: Repositorio responsable de manejar los archivos de código.
- `src/Infrastructure/Persistence/TestRepository.php`: Repositorio responsable de manejar los archivos de test y ejecutar las pruebas.
- `src/Console/Command/RunCommand.php`: Comando de consola para ejecutar la herramienta.
- `bootstrap.php`: Archivo de bootstrap para inicializar y ejecutar el servicio.

## Contribuir

Las contribuciones son bienvenidas. Por favor, abre un issue o envía un pull request para contribuir al proyecto.

## Licencia

Este proyecto está licenciado bajo la Licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más detalles.
