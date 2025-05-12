# Andrei DeepPink

## Descripción

El proyecto **Andrei DeepPink** es una aplicación web moderna y minimalista que permite la auditoría de sitios web.  
Entre sus funcionalidades destacan:
- Registro y login de usuarios utilizando contraseñas almacenadas de forma segura (mediante `password_hash()` y `password_verify()`).
- Generación de reportes de auditoría web a través de la clase *DeepPink*.
- Un panel de administración protegido, accesible mediante credenciales definidas en la tabla `admin`, que permite:
  - Ver todos los campos de la tabla `users`.
  - Ver todos los campos de la tabla `reports`.

La aplicación soporta internacionalización mediante un archivo CSV de traducciones.

## Características

- **Registro y Login Seguros:**  
  Las contraseñas se almacenan hasheadas y se validan de forma segura.

- **Panel de Usuario:**  
  Permite generar reportes de auditoría (con resultados agrupados y detallados).

- **Panel de Administración:**  
  Permite al administrador gestionar usuarios y reportes, mostrando todos los campos de las tablas `users` y `reports`.

- **Interfaz Moderna y Minimalista:**  
  Utiliza paletas de colores refrescantes (acento cian), tipografía moderna (Montserrat) y un diseño responsive.

## Estructura del Proyecto

```plaintext
andrei-deeppink/
├── authenticateUser.php      # Función para autenticación de usuarios (para login).
├── DeepPink.php              # Clase para el análisis de URLs y generación de reportes de auditoría.
├── README.txt                # Este archivo de documentación.
├── admin.php                 # Panel de administración (gestión de usuarios y reportes).
├── css/
│   ├── style.css             # Estilos para index.php (dashboard y login).
│   ├── admin.css             # Estilos específicos para el panel de administración.
│   └── register.css          # Estilos para la página de registro.
├── db.sqlite                 # Base de datos SQLite que almacena usuarios, admin y reportes.
├── deeppink.png              # Recurso gráfico (logotipo o imagen representativa).
├── i18n.php                  # Funciones de internacionalización (carga de traducciones desde CSV).
├── index.php                 # Página principal: Login, generación y visualización de reportes.
├── register.php              # Página de registro para nuevos usuarios.
├── translations.csv          # Archivo CSV con las traducciones para varios idiomas.
└── createAdminTableAndUser.php  # Script para crear la tabla "admin" e insertar un usuario administrador. 
```
## Requisitos

- **PHP 7.x o posterior** con la extensión SQLite habilitada.

- Un servidor web (Apache, Nginx, etc.) para probar la aplicación.

- Un navegador moderno para el acceso a la interfaz.

## Instalación y Configuración

1. **Descarga o clona el repositorio** y colócalo en el directorio raíz de tu servidor web. Ejemplo: ```C:\xampp\htdocs\andrei-deeppink\``` (para XAMPP).

2. **Crea la base de datos y el usuario administrador:** Ejecuta el script ```createAdminTableAndUser.php``` (puedes hacerlo desde la línea de comandos o accediendo vía navegador) para crear la tabla ```admin``` y generar el usuario administrativo **andrei** con contraseña **andrei**. Una vez creado, se recomienda eliminar o desactivar este script.

3. **Acceso a la aplicación:**

    - Para el área de usuario, accede a ```index.php``` y registra nuevos usuarios o inicia sesión con una cuenta existente.

    - Para el área administrativa, accede a ```admin.php``` y utiliza las credenciales del administrador (andrei/andrei).

## Uso

- **Registro y Login de Usuarios:** Utiliza la página ```register.php``` para crear nuevos usuarios. Luego, inicia sesión desde ```index.php``` para generar reportes de auditoría.

- **Panel de Administración:** Desde ```admin.php```, tras iniciar sesión con el usuario administrador, podrás:
    - **Manage Users:** Ver todos los detalles (ID, Username, Password, Name, Email, Created At) de la tabla ```users```.
    - **Manage Reports:** Ver todos los detalles (ID, User ID, URL, Report HTML, Created At) de la tabla ```reports```.

## Personalización

- **Estilos:** Puedes modificar los archivos CSS ubicados en la carpeta ```css``` para adaptar el diseño a tus necesidades.

- **Traducciones:** Edita el archivo ``translations.csv`` para actualizar o agregar nuevos textos en los distintos idiomas soportados.

- **Funcionalidades:** El código PHP es modular, por lo que puedes extenderlo o modificarlo para agregar nuevas funcionalidades según los requerimientos.

## Licencia

Licencia MIT