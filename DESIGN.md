# Diseño

Herramienta de gestión de pruebas utilizada en un aula o escritorio, con luz habitual y a menudo proyectada. Tema claro para el contenido y barra lateral oscura.

Identidad «traje de vuelo»: barra lateral azul marino profundo (no negro) y acento naranja internacional. El naranja identifica marca, acción principal y selección, y aparece poco para que resalte. Los colores de estado no se usan como adorno: verde para Éxito y Cerrado, carmesí para Fallo y Crítica, azul para Abierto y violeta para En progreso. Éxito y Fallo llevan además ✓ y ✕, de modo que no dependen solo del color. Sobre el naranja el texto va en tinta oscura, porque el blanco no alcanza contraste suficiente.

Tokens OKLCH en `apps/bus/public/assets/testing.css`: fondo 0.972/0.004/262, superficie 0.997/0.001/262, tinta 0.25/0.045/264, barra lateral 0.27/0.05/262, acento 0.69/0.2/41, éxito 0.62/0.13/160 y fallo 0.47/0.18/12.

Tipografía IBM Plex Sans para texto e IBM Plex Mono para datos: códigos CP y BUG, cifras, encabezados de tabla y etiquetas. Los archivos WOFF2 (subconjunto latino, licencia SIL OFL 1.1) se sirven desde `apps/bus/public/assets/fonts`, sin CDN, para que la aplicación funcione sin conexión. Tamaño base de 16 px.

Navegación por flujo de trabajo: Pruebas (Dashboard, Casos de prueba, Incidentes), Proyecto (Formularios 1–10) y Sistema bajo prueba (Buscador). La barra superior muestra la ruta actual y el estado real del Bus leído de `/health`. El dashboard resume los casos en una tira de resultados: un segmento por caso, verde o carmesí. La ficha del caso tiene pestañas (Detalle, Diseño de la prueba, Incidentes) y señala el formulario que corresponde a su técnica.

Login y registro comparten una tarjeta centrada sobre el marino. En móvil el menú se despliega con un botón, los campos se apilan y las tablas conservan desplazamiento local. El CSS original del Bus se conserva como fuente; la presentación integrada utiliza testing.css.
