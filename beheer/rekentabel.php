<?php
// De oude jeugd/senior-rekentabel is vanaf fase 2.5 geen beheersource meer.
// Configureerbare Lidmaatschapstypen zijn de enige bron voor nieuwe tarieven.
// Deze route blijft alleen bestaan voor oude bookmarks.
header('Location: lidmaatschap.php', true, 308);
exit;
