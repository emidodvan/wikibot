wikibot
=======

Bot de mediawiki programado en PHP que usa la API.

Requerimientos
==============

*Tener instalado PHP.
*De preferencia usarlo en una wiki con las versión más nueva de mediawiki.
*De preferencia tener el flag de bot en la wiki.

Usando wikibot
==============

Para poder usar las funcines de wikibot, lo que tienes que hacer es bastante sencillo.

*Añadir en un archivo php: include 'wikibot.php';
*Crear un nuevo objeto bot: $mibot = new bot;

Una vez hecho esto, ya podras utilizar las funciones de wikibot. 
Ejemplo de login: $bot->login('mibot', 'micontraseña');
