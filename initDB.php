<?php

function initDB() {
  echo "Le tabelle Stato e Contenuto sono state create o erano già presenti!\n";
  $db = JFactory::getDBO();
  $query = $db->getQuery(true);
  $query = "CREATE TABLE IF NOT EXISTS `#__stato` ( 
  `id_stato` INT NOT NULL PRIMARY KEY, 
  `nome` VARCHAR(50) NOT NULL)";
  $db->setQuery($query)->execute();

  $nomi = array('under review', 'approved', 'rejected', 'draft');
  $columns = array('id_stato', 'nome');

  for ($x = 0; $x <= 3; $x++) {
      $values = array(':id_stato', ':nome');
      $id_stato = 1 + $x;

      $query = $db->getQuery(true)
              ->insert($db->quoteName('#__stato'))
              ->columns($db->quoteName($columns))
              ->values(implode(',', $values))
              ->bind($values[0], $id_stato, Joomla\Database\ParameterType::INTEGER)
              ->bind($values[1], $nomi[$x], Joomla\Database\ParameterType::STRING);

      try {
          $db->setQuery($query)->execute();
      } 

      catch (Exception $e) {
          if ($e->getCode() == 1062) {
              echo("Lo stato '" . $nomi[$x] . "' già esiste!!\n");
          } 

          else {
              echo 'Caught exception: ',  $e->getMessage(), "\n";
          }
      }
  } 

  $query = "CREATE TABLE IF NOT EXISTS `#__contenuto` ( 
  `id_contenuto` INT NOT NULL, 
  `versione` INT NOT NULL DEFAULT 1, 
  `id_autore` INT NOT NULL, 
  `id_categoria` INT NOT NULL, 
  `id_stato` INT NOT NULL DEFAULT 4, 
  `is_visible` BOOL NOT NULL DEFAULT 0, 
  `titolo` VARCHAR(250) NOT NULL, 
  `sottotitolo` VARCHAR(250) NOT NULL, 
  `html_abstract` MEDIUMTEXT NOT NULL, 
  `html_full` MEDIUMTEXT NOT NULL, 
  `data_pubblicazione` DATETIME DEFAULT NULL, 
  `posizione` INT NOT NULL DEFAULT 0,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  CONSTRAINT PRIMARY KEY(id_contenuto, versione), 
  FOREIGN KEY(id_stato) REFERENCES #__stato(id_stato), 
  FOREIGN KEY(id_categoria) REFERENCES #__categories(id))";

  $db->setQuery($query)->execute();

}

if (isset($_POST['submit'])) {
initDB();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document</title>
</head>
<body>
    <form action="" method="POST">
      <input type="submit" name="submit">
    </form>
</body>
</html>
