<?php

error_reporting(E_ERROR | E_PARSE);

function checkToken() {
	$errPage = file_get_contents('https://www.smartour.net/auth-error');
   	JSession::checkToken() or jexit($errPage);
}

function throwError() {
	header('Location: https://www.smartour.net/error');
	exit;
}

function getIdCategoria() {
	$app = JFactory::getApplication();
   	$section = $app->getMenu()->getActive()->title; 
   	$category = "Contents" . str_replace(' ', '', $section);
   	$db = JFactory::getDBO();

    $query = $db->getQuery(true)
                  ->select($db->qn('id'))
                  ->from($db->qn('#__categories'))
                  ->where($db->qn('title') . ' = :category')
                  ->bind(':category', $category, Joomla\Database\ParameterType::STRING);
    return $db->setQuery($query)->loadResult();    

}

function getFormData($fields) {
	 $str = 'STRING';
	 $jinput = JFactory::getApplication()->input;
     $filter = JFilterInput::getInstance();
     $value1 = $jinput->get($fields[0], '', $str);
     $value1 = $filter->clean($value1, $str);
     $value2 = $jinput->get($fields[1], '', $str);
     $value2 = $filter->clean($value2, $str); 
     $value3 = JComponentHelper::filterText($jinput->post->get($fields[2], '', 'raw'));
     $value4 = JComponentHelper::filterText($jinput->post->get($fields[3], '', 'raw'));
	 return array($value1, $value2, $value3, $value4);
}

function getArticleSlug(Object $art) {
	$section = strtolower(JFactory::getApplication()->getMenu()->getActive()->title);
	$section = str_replace(' ', '-', $section);
	$general = $section . '/' . $art->id_contenuto;
	
	if ($art->is_visible) {	
		return $general;
	}
	
	return $general . '-v' . $art->versione;
}

function getModEmails() {
	$db = JFactory::getDBO();
	$mod_group = 5;
    $query = $db->getQuery(true)
                ->select(array($db->qn('email')))           
                ->from($db->qn('#__users', 'a'))  
                ->join('INNER', $db->qn('#__user_usergroup_map', 'b') . ' ON ' . $db->qn('a.id') . ' = ' 								. $db->qn('b.user_id'))
				->where($db->qn('b.group_id') . ' = :mod_group')
				->bind(':mod_group', $mod_group, Joomla\Database\ParameterType::INTEGER);
    return $db->setQuery($query)->loadColumn();
}

function mailMods($id, $title, $abstract, $version=1) {
		$app = JFactory::getApplication();
 		$section1 = $app->getMenu()->getActive()->title;
		$autore = JFactory::getUser()->username;
		$mailer = JFactory::getMailer();
		$config = JFactory::getConfig();
        $from = array( 
            $config->get('mailfrom'),
            $config->get('fromname') 
        );
		$section2 = strtolower($section1);
        $section2 = str_replace(' ', '-', $section2);
        $general = 'https://www.smartour.net/' . $section2 . '/' . $id;
		$url = $general . '-v' . $version;

		$subject = "[smartour] - New article under review";
        $body = $autore . " has submitted for review an article titled '" . $title . "', and available " . "<a href='" . $url . "'>here</a>" . " in the " . $section1 . " section.\n" . "Here is the abstract:\n" . $abstract;

       	$to = getModEmails();
        $mailer->setSender($from);
        $mailer->addRecipient($to);
        $mailer->setSubject($subject);
		$mailer->Encoding = 'base64';
		$mailer->isHTML();
        $mailer->setBody($body);
        $mailer->send();
}

function getUsernameFromId($id) {
	$db = JFactory::getDBO();
	$query = $db->getQuery(true)
                ->select($db->qn('username'))
                ->from($db->qn('#__users'))
                ->where($db->qn('id') . ' = :id')
                ->bind(':id', $id, Joomla\Database\ParameterType::INTEGER);
   	
   	return $db->setQuery($queryAutore)->loadResult();
}

function deleteContenuto($db, int $id_del, int $vers_del) {
	$int = Joomla\Database\ParameterType::INTEGER;
	$db = JFactory::getDBO();

	$db->transactionStart();
  	$query = $db->getQuery(true) 
                ->delete($db->qn('#__contenuto'))
                ->where($db->qn('id_contenuto') . ' = :id_cont')
                ->where($db->qn('versione') . ' = :vers')
                ->bind(':id_cont', $id_del, $int)
                ->bind(':vers', $vers_del, $int);    
		
	$db->setQuery($query)->execute();	
}

function insertContenuto($db, $is_comp, $cols, $vals, $formFields) {
   $bool = Joomla\Database\ParameterType::BOOLEAN;
   $int = Joomla\Database\ParameterType::INTEGER;
   $str = Joomla\Database\ParameterType::STRING;   

   if ($is_comp) {
       $id_stato = 1;
	   array_push($cols, 'id_stato');
       array_push($vals, ':id_stato');
   }	

   $id_categoria = getIdCategoria();
   $query = $db->getQuery(true)
		   	   ->select('MAX(' . $db->qn('id_contenuto') . ')')
		       ->from($db->qn('#__contenuto'));

   $id_contenuto = 1 + $db->setQuery($query)->loadResult();

   $query = $db->getQuery(true)
               ->select('MIN(' . $db->qn('posizione') . ')')
               ->from($db->qn('#__contenuto'))
			   ->where($db->qn('id_categoria') . ' = :id_categoria')
			   ->bind(':id_categoria', $id_categoria, $int);
   
   $posizione = $db->setQuery($query)->loadResult();

   if (is_null($posizione)) {
		$posizione = 0;
   }
   
   $user = JFactory::getUser();
   $id_autore = (int) $user->id;
   list($titolo, $sottotitolo, $html_abstract, $html_full) = getFormData($formFields);

   $query = $db->getQuery(true)
               ->insert($db->qn('#__contenuto'))
               ->columns($db->qn($cols))
               ->values(implode(',', $vals))
               ->bind($vals[0], $id_contenuto, $int)
               ->bind($vals[1], $id_autore, $int)
               ->bind($vals[2], $id_categoria, $int)
               ->bind($vals[3], $titolo, $str)
               ->bind($vals[4], $sottotitolo, $str)
               ->bind($vals[5], $html_abstract, $str)
               ->bind($vals[6], $html_full, $str)
               ->bind($vals[7], $posizione, $int);

   if ($is_comp) { 
   		$query->bind($vals[8], $id_stato, $int);
        if (!in_array(5, $user->getAuthorisedGroups())) {
        	mailMods($id_contenuto, $titolo, $html_abstract);
        }           
   }       

   $db->setQuery($query)->execute();		 
}
  
 function updateContenuto($db, $pvals, $cols, $vals, $formFields) {
	 list($old_pub, $new_auth, $is_new_from, $is_dra, $is_cauth, $id_st, $is_comp, $is_app, $is_pub, $is_clone, $id_u, $new_versione, $id_autore_update, $id_cat_update, $is_v, $pos) = $pvals;
	 $user = JFactory::getUser();
	 $is_mod = in_array(5, $user->getAuthorisedGroups());
	 
	 $bool = Joomla\Database\ParameterType::BOOLEAN;
	 $int = Joomla\Database\ParameterType::INTEGER;
	 $str = Joomla\Database\ParameterType::STRING;
	
	 if ($new_auth == 0) {
		list($new_titolo, $new_sottotitolo, $new_html_abstract, $new_html_full) = getFormData($formFields);
 	 }
	
     $query = $db->getQuery(true);
	 if ($is_comp) {
         $id_stato = 1;
         array_push($cols, 'id_stato');
         array_push($vals, ':id_stato');
   	 }	

	 if ($is_clone || $is_new_from) {
		   if ($is_new_from) {
				$queryId = $db->getQuery(true)
   							  ->select('MAX(' . $db->qn('id_contenuto') . ')')
                      		  ->from($db->qn('#__contenuto'));

   				$id_u = 1 + $db->setQuery($queryId)->loadResult();
				unset($cols[1]);
				unset($vals[1]);
		   }

           $query->insert($db->qn('#__contenuto'))
                 ->columns($db->qn($cols))
                 ->values(implode(',', $vals))
                 ->bind($vals[0], $id_u, $int)
                 ->bind($vals[2], JFactory::getUser()->id, $int)
                 ->bind($vals[3], $id_cat_update, $int)
                 ->bind($vals[4], $pos, $int)
                 ->bind($vals[5], $new_titolo, $str)
                 ->bind($vals[6], $new_sottotitolo, $str)
                 ->bind($vals[7], $new_html_abstract, $str)
                 ->bind($vals[8], $new_html_full, $str);
			
			if ($is_clone) {
					$query->bind($vals[1], $new_versione, $int);
			}
			
			if ($is_comp) {
				$query->bind($vals[9], $id_stato, $int);

				if (!$is_mod) {
						mailMods($id_u, $new_titolo, $new_html_abstract);
				}
			}
      }
	  
	  else {
              $conditions = array(
                          $db->qn('id_contenuto') . ' = :id_update',
                          $db->qn('versione') . ' = :versione_update'
                         );

			if ($new_auth == 0) {
                $fields = array(
                                $db->qn('titolo') . ' = :new_titolo',
                                $db->qn('sottotitolo') . ' = :new_sottotitolo',
                                $db->qn('html_abstract') . ' = :new_html_abstract',
                                $db->qn('html_full') . ' = :new_html_full'
                                );
			}

			else {
				$fields = array(
                              $db->qn('id_autore') . ' = :new_autore'
                              );
			}

			if ($is_comp) {
					$id_stato = 1;
					array_push($fields, $db->qn('id_stato') . ' = :id_stato');
			 }

			if ($is_app || $is_pub) {
					$id_stato = 2;
					array_push($fields, $db->qn('id_stato') . ' = :id_stato');

					if ($is_pub) {
						$is_visible = 1;
						array_push($fields, $db->qn('is_visible') . ' = :is_visible');
						array_push($fields, $db->qn('data_pubblicazione') . ' = NOW()');

						if ($old_pub != 0) {
                            $hidden = 0;
                            $fieldsOld = array(
                                                $db->qn('is_visible') . ' = :hidden',
                                                $db->qn('data_pubblicazione') . ' = NULL'
                                              );

                            $conditionsOld = array(
                                                  $db->qn('id_contenuto') . ' = :id_update',
                                                  $db->qn('versione') . ' = :old_pub'
                                                  );
							
                            $queryOld = $db->getQuery(true)
                                           ->update($db->qn('#__contenuto'))
                                           ->set($fieldsOld)
                                           ->where($conditionsOld)
                                           ->bind(':id_update', $id_u, $int)
                                           ->bind(':old_pub', $old_pub, $int)
                                           ->bind(':hidden', $hidden, $bool);
                         	
							$db->setQuery($queryOld)->execute();    
                        }
     			}
			 }

			if ($is_dra ) {
				$id_stato = 4;
				array_push($fields, $db->qn('id_stato') . ' = :id_stato');
			}
			  
              $query->update($db->qn('#__contenuto'))
                    ->set($fields)
                    ->where($conditions)
                    ->bind(':id_update', $id_u, $int)
                    ->bind(':versione_update', $new_versione, $int);

			  if ($new_auth == 0) {
					$query->bind(':new_titolo', $new_titolo, $str)
                          ->bind(':new_sottotitolo', $new_sottotitolo, $str)
                          ->bind(':new_html_abstract', $new_html_abstract, $str)
                          ->bind(':new_html_full', $new_html_full, $str);
			   }

			   else {
					$query->bind(':new_autore', $new_auth, $int);
			   }

			  if ($is_dra || $is_comp || $is_app || $is_pub) {
                    $query->bind(':id_stato', $id_stato, $int);

                    if ($is_pub) {
                            $query->bind(':is_visible', $is_visible, $bool);
                    }			

                    if ($is_comp && !$is_mod) {
							$queryStato = $db->getQuery(true)
                                             ->select($db->qn('id_stato'))
                                             ->from($db->qn('#__contenuto'))
                                             ->where($conditions)
                                             ->bind(':id_update', $id_u, $int)
                                             ->bind(':versione_update', $new_versione, $int);

							$old_stato = $db->setQuery($queryStato)->loadResult();

							if ($old_stato != 1) {
	                            mailMods($id_u, $new_titolo, $new_html_abstract);
							}
                    }
			  }	
	  }
	  
      $db->setQuery($query)->execute();
  }
  
  if (isset($_POST['insert']) || isset($_POST['insert-review'])) {
		checkToken();
		$is_complete = isset($_POST['insert-review']);

      	$columns = array('id_contenuto', 'id_autore', 'id_categoria', 'titolo', 'sottotitolo', 'html_abstract', 'html_full', 'posizione');
   		$values = array(':id_contenuto', ':id_autore', ':id_categoria', ':titolo', ':sottotitolo', ':html_abstract', ':html_full',
':posizione');
		$ffields = array('titolo', 'sottotitolo', 'html_abstract', 'html_full');

		$db = JFactory::getDBO();

		try {
			$db->transactionStart();
			insertContenuto($db, $is_complete, $columns, $values, $ffields);
			$db->transactionCommit();
		}

		catch (Exception $e) {
			$db->transactionRollback();
    		throwError();
		}
		
		if ($is_complete) {
			unset($_POST['insert-review']);
		}
		
		else {
			unset($_POST['insert']);
		}
  }	

  if (isset($_POST['update']) || isset($_POST['update-draft']) || isset($_POST['update-review']) || isset($_POST['update-approve']) || isset($_POST['update-publish']) || isset($_POST['update-author'])) {
       	checkToken();
		$is_draft = isset($_POST['update-draft']);
		$is_complete = isset($_POST['update-review']);
		$is_approve = isset($_POST['update-approve']);
		$is_publish = isset($_POST['update-publish']);
		$old_vpub2 = isset($_POST['old_vpub2']) ? $_POST['old_vpub2'] : 0;
		$id_stato_update = isset($_POST['id_stato_update'])? $_POST['id_stato_update'] : 0;
		$is_cauth = isset($_POST['is_cauth'])? $_POST['is_cauth'] : 0;
		$new_autore = isset($_POST['new_autore'])? $_POST['new_autore'] : 0;
	
		$post_values = array($old_vpub2, $new_autore, $_POST['is_new_from'], $is_draft, $is_cauth, $id_stato_update, $is_complete, $is_approve, $is_publish, $_POST['is_clone'], $_POST['id_update'], $_POST['versione_update'], $_POST['id_autore_update'], $_POST['id_cat_update'], $_POST['is_visible_update'], $_POST['posizione_update']);

		$columns = array('id_contenuto', 'versione', 'id_autore', 'id_categoria', 'posizione', 'titolo', 'sottotitolo', 'html_abstract', 'html_full');

   		$values = array(':id_contenuto', ':versione', ':id_autore_update', ':id_categoria', ':posizione', ':titolo', ':sottotitolo', ':html_abstract', ':html_full');

		if ($new_autore != 0) {
			$ffields = array('new_autore');
   		}
		
		else {
			$ffields = array('new_titolo', 'new_sottotitolo', 'new_html_abstract', 'new_html_full');
		}

		$db = JFactory::getDBO();

		try {
			$db->transactionStart();
			updateContenuto($db, $post_values, $columns, $values, $ffields);
			$db->transactionCommit();
		}

		catch (Exception $e) {
			$db->transactionRollback();
			throwError();
		}

		if ($is_approve) {
			unset($_POST['update-approve']);
		}

		elseif ($is_publish) {
			unset($_POST['update-publish']);
		}

		elseif ($is_complete) {
			unset($_POST['update-review']);
		}

		elseif ($is_draft) {
			unset($_POST['update-draft']);
		}
		
		elseif ($new_autore != 0) {
			unset($_POST['update-author']);
		}

		else { unset($_POST['update']); }
    	
  }

  if (isset($_POST['delete'])) {
		checkToken();
		$db = JFactory::getDBO();

		try {
			$db->transactionStart();
			deleteContenuto($db, $_POST['id_cont'], $_POST['vers_cont']);
			$db->transactionCommit();
		}

		catch (Exception $e) {
			$db->transactionRollback();
    		throwError();
		}
     	
     	unset($_POST['delete']);
  }

  if (isset($_POST['review']) || isset($_POST['approve']) || isset($_POST['refute'])) {
		checkToken();
		$int = Joomla\Database\ParameterType::INTEGER;
		$id_change = $_POST['id_cont'];
		$vers_change = $_POST['vers_cont'];

		if (isset($_POST['review'])) {
			$id_stato = 1;
			unset($_POST['review']);
		}
			 
		elseif (isset($_POST['approve'])) { 
			$id_stato = 2;
			unset($_POST['approve']); 
		}

		else { 
			$id_stato = 3;
			unset($_POST['refute']); 
		}

		$conditions = array(
						$db->qn('id_contenuto') . ' = :id_change',
						$db->qn('versione') . ' = :vers_change'
                       );
		
		$db = JFactory::getDBO();

		try {
			$db->transactionStart();
			$query = $db->getQuery(true)
                        ->update($db->qn('#__contenuto'))
                        ->set($db->qn('id_stato') . ' = :id_stato')
                        ->where($conditions)
                        ->bind(':id_change', $id_change, $int)
                        ->bind(':vers_change', $vers_change, $int)
                        ->bind(':id_stato', $id_stato, $int);
	
			$db->setQuery($query)->execute();
			$db->transactionCommit();
		}

		catch (Exception $e) {
			$db->transactionRollback();
    		throwError();
		}
     	
  }

  if (isset($_POST['publish']) || isset($_POST['unpublish'])) {
		checkToken();
		$bool = Joomla\Database\ParameterType::BOOLEAN;
	 	$int = Joomla\Database\ParameterType::INTEGER;
		$id_pub = $_POST['id_cont'];
		$old_vpub = isset($_POST['old_vpub']) ? $_POST['old_vpub'] : 0;
		$vers_pub = $_POST['vers_cont'];
		$is_visible = isset($_POST['publish']) ? 1 : 0;
		
		if (isset($_POST['publish'])) {
			$fields = array(
							$db->qn('is_visible') . ' = :is_visible',
							$db->qn('data_pubblicazione') . ' = NOW()'
							);
			unset($_POST['publish']);
		}
			 
		if (isset($_POST['unpublish'])) { 
			$fields = array(
							$db->qn('is_visible') . ' = :is_visible',
							$db->qn('data_pubblicazione') . ' = NULL'
							);

			unset($_POST['unpublish']); 	
		}

		$conditions = array(
						$db->qn('id_contenuto') . ' = :id_pub',
						$db->qn('versione') . ' = :vers_pub'
                       );

		$db = JFactory::getDBO();

		try {
        	$db->transactionStart();

            if ($old_vpub != 0) {
                $hidden = 0;
                $fieldsOld = array(
                                    $db->qn('is_visible') . ' = :hidden',
                                    $db->qn('data_pubblicazione') . ' = NULL'
                                  );

                $conditionsOld = array(
                                      $db->qn('id_contenuto') . ' = :id_pub',
                                      $db->qn('versione') . ' = :old_vpub'
                                      );


                $query = $db->getQuery(true)
                            ->update($db->qn('#__contenuto'))
                            ->set($fieldsOld)
                            ->where($conditionsOld)
                            ->bind(':id_pub', $id_pub, $int)
                            ->bind(':old_vpub', $old_vpub, $int)
                            ->bind(':hidden', $hidden, $bool);    

                $db->setQuery($query)->execute();
            }

            $query = $db->getQuery(true)
                        ->update($db->qn('#__contenuto'))
                        ->set($fields)
                        ->where($conditions)
                        ->bind(':id_pub', $id_pub, $int)
                        ->bind(':vers_pub', $vers_pub, $int)
                        ->bind(':is_visible', $is_visible, $bool);
	
    		$db->setQuery($query)->execute();
            $db->transactionCommit();    
         }

         catch (Exception $e) {
                $db->transactionRollback();
                throwError();
         }
  }

  if (isset($_POST['approve-publish']) || isset($_POST['unpublish-review'])) {
		checkToken();
		$bool = Joomla\Database\ParameterType::BOOLEAN;
	 	$int = Joomla\Database\ParameterType::INTEGER;
		$id_pub = $_POST['id_cont'];
		$old_vpub = isset($_POST['old_vpub']) ? $_POST['old_vpub'] : 0;
		$vers_pub = $_POST['vers_cont'];
		$id_stato = isset($_POST['approve-publish']) ? 2 : 1;
		$is_visible = isset($_POST['approve-publish']) ? 1 : 0;

		if (isset($_POST['approve-publish'])) {
			$fields = array(
							$db->qn('id_stato') . ' = :id_stato',
							$db->qn('is_visible') . ' = :is_visible',
							$db->qn('data_pubblicazione') . ' = NOW()'
							);
			unset($_POST['approve-publish']);
		}
			 
		if (isset($_POST['unpublish-review'])) { 
			$fields = array(
							$db->qn('id_stato') . ' = :id_stato',
							$db->qn('is_visible') . ' = :is_visible',
							$db->qn('data_pubblicazione') . ' = NULL'
							);

			unset($_POST['unpublish-review']); 	
		}

		$conditions = array(
						$db->qn('id_contenuto') . ' = :id_pub',
						$db->qn('versione') . ' = :vers_pub'
                       );

		$db = JFactory::getDBO();

		try {
        	$db->transactionStart();

            if ($old_vpub != 0) {
                $hidden = 0;

                $fieldsOld = array(
                                    $db->qn('is_visible') . ' = :hidden',
                                    $db->qn('data_pubblicazione') . ' = NULL'
                                  );

                $conditionsOld = array(
                                      $db->qn('id_contenuto') . ' = :id_pub',
                                      $db->qn('versione') . ' = :old_vpub'
                                      );


                    $query = $db->getQuery(true)
                                ->update($db->qn('#__contenuto'))
                                ->set($fieldsOld)
                                ->where($conditionsOld)
                                ->bind(':id_pub', $id_pub, $int)
                                ->bind(':old_vpub', $old_vpub, $int)
                                ->bind(':hidden', $hidden, $bool);

                    $db->setQuery($query)->execute();			
			}
	
            $query = $db->getQuery(true)
                        ->update($db->qn('#__contenuto'))
                   	    ->set($fields)
                        ->where($conditions)
                        ->bind(':id_pub', $id_pub, $int)
                        ->bind(':vers_pub', $vers_pub, $int)
                        ->bind(':id_stato', $id_stato, $int)
                        ->bind(':is_visible', $is_visible, $bool);
	
			$db->setQuery($query)->execute();
            $db->transactionCommit();    
        }

        catch (Exception $e) {
                $db->transactionRollback();
                throwError();    
        }		
  }

  if (isset($_POST['move-up']) || isset($_POST['move-down'])) { 
             checkToken();
			 $int = Joomla\Database\ParameterType::INTEGER;
			 $id_move = $_POST['id_cont'];
			 $vers_move = $_POST['vers_cont'];
			 $var = isset($_POST['move-up']) ? 1 : -1;

			 if (isset($_POST['move-up'])) {
				 unset($_POST['move-up']);
			 }
			 
			 else { unset($_POST['move-down']); }

			 $conditions = array(
						$db->qn('id_contenuto') . ' = :id_move',
						$db->qn('versione') . ' = :vers_move'
                       );

             $db = JFactory::getDbo();
             $query  = $db->getQuery(true)
						  ->select($db->qn('posizione'))
						  ->from($db->qn('#__contenuto'))
						  ->where($conditions)
						  ->bind(':id_move', $id_move, $int)
						  ->bind(':vers_move', $vers_move, $int);
						  
			 $new_posizione = $var + $db->setQuery($query)->loadResult();

			 try {
                $query = $db->getQuery(true)
                            ->update($db->qn('#__contenuto'))
                            ->set($db->qn('posizione') . ' = :posizione')
                            ->where($conditions)
                            ->bind(':id_move', $id_move, $int)
                            ->bind(':vers_move', $vers_move, $int)
                            ->bind(':posizione', $new_posizione, $int);
	
				$db->setQuery($query)->execute();
                $db->transactionCommit();
            }

            catch (Exception $e) {
                $db->transactionRollback();
                throwError();
            }
   }

?>

<!DOCTYPE html>
<html>  
<head>
  	<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400&display=swap" rel="stylesheet">
  
	<style>

		.form {
            display: flex;
            flex-direction: column;
        }
      
        .dropFiltri { display:none; }
      
      	.form #titolo, #new_titolo, #sottotitolo, #new_sottotitolo {
          	border: 0.5px solid gray;
            border-radius: 10px;
            font-family: sans-serif;
            font-size: 1vw;
            margin-top: 1vw;
            padding: 1vw;
          	background-color: white;
          	width: 55vw;
        }
      
      	#html_abstract, #new_html_abstract, #html_full, #new_html_full {
        	border: 0.5px solid gray;
            border-radius: 10px;
            font-family: sans-serif;
            font-size: 1vw;
            margin-top: 1vw;
            padding: 1vw;
          	background-color: white;
        	width: 55vw;
        	resize: both;
      	}
     
      	.abstract-articolo  a {color: #ca6702;}
	  	.abstract-articolo a:link { text-decoration: none; }
	  	.abstract-articolo a:visited { text-decoration: none; }
	  	.abstract-articolo a:hover { text-decoration: none; color: #1d3557; }
	  	.abstract-articolo a:active { text-decoration: none; }
      
      	#new_autore {
        	border: 0.5px solid gray;
            border-radius: 10px;
            font-family: sans-serif;
            font-size: 1vw;
            margin-top: 1vw;
            padding: 1vw;
          	background-color: white;
        	width: 20vw;
        	resize: both;
      	}
      
      	.option-btn { border: none; }
      
      	.div1 {
            display: flex;
            flex-direction: column; 
          	font-family: sans-serif;
            width: auto;
      	}

      	.articolo {
          	display: flex;
            flex-direction: row;
            background-color: white;
          	border: solid 2px #ececec;
            box-shadow: rgba(0, 0, 0, 0.1) 0 4px 12px;
            border-radius: 0.5vw;
            height: auto;
            margin-bottom: 1vw;
        }
      
      	.articolo:hover { border: solid 2px orange; }
      
        .elementi-articolo {
            display: flex;
            flex-direction: column;
            padding-top: 2vw;
          	padding-bottom: 2vw;
            border: 2vw;
            width: 55vw;
            align-self: center;
          	justify-items: center;
        }

      	.abstract-container {
          	width: 45vw;
          	padding: 2vw;
            border: 2vw;
		}
      
      	.link-read-more {
      		text-decoration: none;
        	display: flex;
        	justify-content: flex-start;
        	align-self: flex-start;
        	color: #ca6702;
        	font-size: 0.9em;
      	}

        .abstract-articolo {
     		justify-content: center;
          	align-items: center;
            align-self: center;
        }  
      
      	.dropbtn {
            background-color: orange;
            color: white;
            padding: 16px;
            font-size: 1.2em;
            border: none;
            cursor: pointer;
            border-radius: 0.5vw;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
      
        .dropdown {
            position: relative;
            display: inline-block;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
      
        .dropdown-content {
            display: none;
            position: absolute;
     		background-color: #f1f1f1;
            min-width: 7vw;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            overflow: none;
        }

        .dropdown-content input {
            font-size: 1.1em;
            font-family: 'Montserrat', sans-serif;
            font-weight: 400;
            color: black;
            padding: 0.3vw;
            margin: 0;
            text-decoration: none;
            display: block;
            min-width: 100%;
            background-color: #f1f1f1;
        }

		.dropdown-content input:hover {background-color: #D2770F;}
		.dropdown:hover .dropdown-content {display: block;}
		.dropdown:hover .dropbtn {background-color: #D2770F;}

		.filtri-content input:hover {background-color: #D2770F;}
		.parent-filtri:hover .filtri-content {display: block;}
		.parent-filtri:hover .filtri {background-color: #D2770F;}
      
      	.filtri {
       		display: flex;
       		flex-direction: row;
 			align-items: center;
        	flex-wrap: wrap;
      	}
      
       .form-filtri {
       		width: 70vw;
         	margin-top: 5px;
      	}
     
      .parent-filtri  input[type="radio"]{ display: none; }
      .parent-filtri input:checked + label { background: #D3D3D3; }
      .parent-filtri  label:hover { background: #ECECEC; }
      
      .parent-filtri input:checked {
        	width: 0;
        	height: 0;
        	padding: 0;
        	margin: 0;
        	opacity: 0;
      }

      .div-crea-articolo {
        	display: flex;
        	width: auto;
        	align-items: center;
      }
      
      .parent-filtri {
        	display: flex;
        	flex-direction: row;
        	align-items: baseline;
        	transition: 0.15s ease-in-out;
      }

	  .action-btn {
			padding: 16px;
            font-size: 1.2em;
            cursor: pointer;
            border-radius: 15px;
            align-items: center;
            justify-content: center;
            margin: 0.5vw;
            white-space: nowrap;
        	background-color: orange;
            color: white;
            border: none;
          	transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
	  }
      
      .action-btn: hover { background-color: #D2770F; }
      .medium-btn { background-color: #C8681E; }
      .medium-btn:hover { background-color: #B65427; }
      .darkest-btn { background-color: #912B3B; }
      .darkest-btn:hover { background-color: #954b56; }
      
      
      .cancel-btn {
			padding: 16px;
            font-size: 1.2em;
            cursor: pointer;
            border-radius: 15px;
            align-items: center;
            justify-content: center;
            margin: 0.5vw;
            white-space: nowrap;
        	background-color: grey;
            color: black;
            border: none;
        	transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
	  }
      
      .cancel-btn: hover { background-color: #696969; }
      
       label {
        	text-align: center;
        	background: #ffffff;
        	box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        	color: #222222;
        	padding: 16px;
        	font-size: 1.2em;
        	border: none;
        	padding: 0.8%;
        	margin: 0.5%;
        	margin-bottom: 30px;
        	cursor: pointer;
        	border-radius: 8px;
      }
      
      #countdown2 {
         	display: flex;
         	justify-content: flex-end;
    		color: black;
    		font-family: sans-serif;
    		font-size: 2em;
    		font-weight: bold;
    		text-decoration: none;
	  }
      
      .new_autore option {
            font-size: 1.1em;
            font-family: 'Montserrat', sans-serif;
            font-weight: 400;
            color: black;
            padding: 0.6vw;
            margin: 0;
            text-decoration: none;
            display: block;
            min-width: 100%;
            background-color: #f1f1f1;
      }
      
	  @media screen and (max-width: 780px) {

            .dropFiltri {
                  display: initial;  
                  background-color: white;
                  border: solid 1px orange;
                  color: black;
                  padding: 16px;
                  font-size: 1.2em;
                  cursor: pointer;
                  border-radius: 15px;
                  align-items: center;
                  justify-content: center;
                  margin: 0.5vw;
                  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }

            .filtri { display:none; }

            .filtri-content {
                  display: none;
                  background-color: #f1f1f1;
                  min-width: 10vw;
                  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
                  z-index: 1;
                  overflow: none;
                  position: absolute;
                  right: 3vw;
                  top: 7.5vh;
            }

            .filtri-content label {
                  font-size: 1.1em;
                  font-family: 'Montserrat', sans-serif;
                  font-weight: 400;
                  color: black;
                  padding: 1.4vw;
                  margin: 0;
                  text-decoration: none;
                  display: block;
                  min-width: 7vw;
                  background-color: #f1f1f1; 
                  box-shadow: 0 0 0 0, 0 0 0;
                  border-radius: 0px;
                  white-space: nowrap;
            }

            .abstract-articolo { margin: 5vw; }

            .form #titolo, #new_titolo, #sottotitolo, #new_sottotitolo, #html_abstract, #new_html_abstract, #html_full, #new_html_full {
                  border: 0.5px solid gray;
                  border-radius: 5px;
                  font-family: sans-serif;
                  font-size: 6vw;
                  margin: 2vw;
                  padding: 1vw;
                  background-color: white;
                  width: 80vw;
                  height: 30vw;
            }

            .form #new_autore {
                  font-size: 1.1em;
                  font-family: 'Montserrat', sans-serif;
                  font-weight: 400;
                  color: black;
                  padding: 0.6vw;
                  margin: 0;
                  text-decoration: none;
                  display: block;
                  min-width: 100%;
                  background-color: #f1f1f1;
            }

            .dropdown {
                  display: flex;
                  justify-content: flex-end;
                  position: absolute;
                  right: 0;
            }

            .dropdown-content {
                  display: none;
                  position: absolute;
                  top: 55px;
            }

            .dropdown-content input {
                  font-size: 1.1em;
                  font-family: 'Montserrat', sans-serif;
                  font-weight: 400;
                  color: black;
                  padding: 1.4vw;
                  margin: 0;
                  text-decoration: none;
                  display: block;
                  min-width: 100%;
                  background-color: #f1f1f1;
            }

            .articolo {
                  display: flex;
                  flex-direction: column;
                  position: relative;
            }

            .elementi-articolo { width: 70vw; }

      }

    </style>    
</head>
<body>
  
<?php 

  $bool = Joomla\Database\ParameterType::BOOLEAN;
  $int = Joomla\Database\ParameterType::INTEGER;
  $str = Joomla\Database\ParameterType::STRING;

     if (isset($_POST['filtro'])) {
            checkToken();
            $filtro = $_POST['filtro'];
      }

	 if (isset($_POST['filtro2'])) {
		$filtro = $_POST['filtro2'];
	  }

	 if (isset($_POST['filtro_update'])) {
		$filtro = $_POST['filtro_update']; 
	  }

	  $id_categoria = getIdCategoria();
      $user = JFactory::getUser();
      $id_utente = (int) $user->id;
	  $id_stato = 2;
	  $is_visible = 1;
      $conditions = array(
                          $db->qn('id_stato') . ' = :id_stato',
                          $db->qn('is_visible') . ' = :is_visible'
                         );

      $query = $db->getQuery(true)
                  ->select('*')
                  ->from($db->qn('#__contenuto'))
                  ->where($db->qn('id_categoria') . ' = :id_categoria' )
                  ->bind(':id_categoria', $id_categoria, $int);
	

  if (!isset($_POST['edit']) && !isset($_POST['clone-edit']) && !isset($_POST['immediate-edit']) && !isset($_POST['author-edit']) 
		&& !isset($_POST['new-edit'])) { ?>
<div class="grandparent-filtri">
  

<?php 
	  
	if ($user->guest) {
		  	$query->where($conditions)
			      ->bind(':id_stato', $id_stato, $int)
				  ->bind(':is_visible', $is_visible, $bool);
	}	

	else {

	?>
  <div class="parent-filtri" id="parent-filtri">
    
    <div class="div-crea-articolo">
        <button role="button" aria-label="Submit button" class='action-btn' onclick="getElementById('form').style.display = 'block'; getElementById('parent-filtri').style.display = 'none'; set('none');">
                  Submit article
        </button>
    </div> 
    
    <button class="dropFiltri">Filters</button>
  	
    <form action="" method="POST" class="form-filtri">
      <div class="filtri-content">
    <?php 
	  
      if (!isset($filtro) || $filtro == 'tutti-articoli') {
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="tutti" name="tutti-articoli" value="tutti-articoli" checked />
      <?php 
	  } 
	  else { ?>
	  <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="tutti" name="tutti-articoli" value="tutti-articoli" />
      <?php
      } ?>
      
      <label for="tutti">All articles</label>
      <?php 
	  if (isset($filtro) && $filtro == 'miei-articoli') {
             $query->where($db->qn('id_autore') . ' = :id_utente')
                            ->bind(':id_utente', $id_utente, $int);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="miei" name="miei-articoli" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="miei" name="miei-articoli" value="miei-articoli" />
      <?php 
      } ?>
      
      <label for="miei">My articles</label>
      
      <?php 
	  if (isset($filtro) && $filtro == 'articoli-altrui') { 
			$query->where($db->qn('id_autore') . ' != :id_utente')
                   ->bind(':id_utente', $id_utente, $int);
 	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="altrui" name="articoli-altrui" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="altrui" name="articoli-altrui" value="articoli-altrui" />
      <?php 
      } ?>
      
      <label for="altrui">By other authors</label>
      
      <?php 
	  if (in_array(5, $user->getAuthorisedGroups())) {
	  
		if (isset($filtro) && $filtro == 'articoli-approvati') { 
			  $id_stato = 2;
			  $query->where($db->qn('id_stato') . ' = :id_stato')
                    ->bind(':id_stato', $id_stato, $int);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="approvati" name="articoli-approvati" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="approvati" name="articoli-approvati" value="articoli-approvati" />
      <?php 
      } ?>
      
      
      <label for="approvati">Approved</label>
      
      <?php 
	  if (isset($filtro) && $filtro == 'articoli-revisione') { 
			 $id_stato = 1; 
			 $query->where($db->qn('id_stato') . ' = :id_stato')
                    ->bind(':id_stato', $id_stato, $int);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="revisione" name="articoli-revisione" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="revisione" name="articoli-revisione" value="articoli-revisione" />
      <?php 
      } ?>
      
      <label for="revisione">Under review</label>
      
      <?php 
	  if (isset($filtro) && $filtro == 'articoli-rifiutati') { 
			  $id_stato = 3;
			  $query->where($db->qn('id_stato') . ' = :id_stato')
                    ->bind(':id_stato', $id_stato, $int);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="rifiutati" name="articoli-rifiutati" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="rifiutati" name="articoli-rifiutati" value="articoli-rifiutati" />
      <?php 
      } ?>
      
      <label for="rifiutati">Rejected</label>
      
      <?php 
	  if (isset($filtro) && $filtro == 'articoli-pubblicati') { 
			 $is_visible = 1; 
			 $query->where($db->qn('is_visible') . ' = :is_visible')
                    ->bind(':is_visible', $is_visible, $int);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="pubblicati" name="articoli-pubblicati" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="pubblicati" name="articoli-pubblicati" value="articoli-pubblicati" />
      <?php 
      } ?>
      
      <label for="pubblicati">Published</label>
      
      <?php 
	  if (isset($filtro) && $filtro == 'articoli-nascosti') { 
			 $is_visible = 0; 
			 $query->where($db->qn('is_visible') . ' = :is_visible')
                    ->bind(':is_visible', $is_visible, $bool);
	  ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value;" id="nascosti" name="articoli-nascosti" checked />
      <?php 
	  } 
	  else { ?>
      <input type="radio" onclick="document.getElementById('filtro').value=this.value; this.form.submit(); " id="nascosti" name="articoli-nascosti" value="articoli-nascosti" />
      <?php 
      } ?>
      
      <label for="nascosti">Hidden</label>
   <?php } ?>
      <input type="hidden" name ="filtro" id="filtro" value = "" >
      <?php echo JHtml::_('form.token'); ?>
    </div> </form>
    
</div> 
<?php } ?>
   </div>
<?php } ?>  
  <div class="div1">
	<?php

	if ( (!isset($filtro) || $filtro == 'tutti-articoli') && in_array(3, $user->getAuthorisedGroups()) && !in_array(5, $user->getAuthorisedGroups())) {

			  $query = $db->getQuery(true)
                          ->select('*')
                          ->from($db->qn('#__contenuto'))
						  ->where($db->qn('id_autore') . ' = :id_utente') 
                          ->orWhere($conditions)
  						  ->andWhere($db->qn('id_categoria') . ' = :id_categoria' )
                          ->bind(':id_utente', $id_utente, $int) 
                          ->bind(':id_stato', $id_stato, $int)
                          ->bind(':is_visible', $is_visible, $bool)
                          ->bind(':id_categoria', $id_categoria, $int);
	}
	
	$query->order($db->qn('posizione') . ' DESC')
          ->order($db->qn('data_pubblicazione') . ' ASC');
	$contenuti = $db->setQuery($query)->loadObjectList();

	if (isset($_POST['edit']) || isset($_POST['clone-edit']) || isset($_POST['new-edit']) || isset($_POST['immediate-edit'])
		|| isset($_POST['author-edit'])) { 
		   checkToken();
		   $is_simple = isset($_POST['edit']);
		   $is_clone = isset($_POST['clone-edit']);
		   $is_new_from = isset($_POST['new-edit']);
		   $is_new_auth = isset($_POST['author-edit']);
		   $is_immediate = !$is_simple && !$is_clone && !$is_new_from && !$is_new_auth;
 		   if ($is_simple || $is_clone || $is_new_from) {
               $is_mod = in_array(5, $user->getAuthorisedGroups());
			   $is_cauth = $_POST['is_cont_author'];
		   }
		
		   $is_auth = in_array(3, $user->getAuthorisedGroups());
		   $id_edit = $_POST['id_cont'];
		   $old_versione = $_POST['vers_cont'];
		   unset($_POST['edit']);
		   $db = JFactory::getDbo();
		   $query = $db->getQuery(true)
		   			   ->select('MAX(' . $db->qn('versione') . ')')
				       ->from($db->qn('#__contenuto'))
				       ->where($db->qn('id_contenuto') . ' = :id_edit')
				       ->bind(':id_edit', $id_edit, $int);
		   $new_versione = 1 + $db->setQuery($query)->loadResult();	   
		   $columns = array('id_stato', 'is_visible', 'id_autore', 'id_categoria', 'titolo', 'sottotitolo', 'html_abstract', 		
							'html_full', 'posizione');
		   $query = $db->getQuery(true)
                       ->select($db->qn($columns))
                       ->from($db->qn('#__contenuto'))
                       ->where($db->qn('id_contenuto') . ' = :id_edit')
					   ->where($db->qn('versione') . ' = :old_versione')
                       ->bind(':id_edit', $id_edit, $int)
					   ->bind(':old_versione', $old_versione, $int);

 		   list($id_stato_edit, $is_visible_edit, $id_autore_edit, $id_cat_edit, $titolo_edit,
				 $sottotitolo_edit, $htmla_edit, $htmlf_edit, $posizione_edit) = $db->setQuery($query)->loadRow();
  		   if ($is_clone) {
				$versione_edit = $new_versione;
		   }
			
			if (!$is_clone && !$is_new_from) {
				$versione_edit = $old_versione;
			}
	?>
    <div style='display:block' id="form-modifica" class="form">
      <form action="" method="POST">
<?php
		if ($is_new_auth) { 
			$auth_group = 3;
			$mod_group = 5;
			$query = $db->getQuery(true)
                        ->select(array($db->qn('user_id'), $db->qn('username')))           
                        ->from($db->qn('#__users', 'a'))  
                        ->join('INNER', $db->qn('#__user_usergroup_map', 'b') . ' ON ' . $db->qn('a.id') . ' = ' . $db->qn('b.user_id'))
                        ->where($db->qn('b.group_id') . ' = :auth_group')
						->orWhere($db->qn('b.group_id') . ' = :mod_group')
						->bind(':auth_group', $auth_group, $int)
						->bind(':mod_group', $mod_group, $int)
						->order($db->qn('username') . ' ASC');
			
            $autori = $db->setQuery($query)->loadObjectList();
		?>
        <select required name="new_autore" id="new_autore" class="new_autore">
          		<option class="new_autore_option" value="">Please select an author</option>
            <?php foreach ($autori as $a) { ?>
                <option class="new_autore_option" value="<?php echo $a->user_id; ?>"><?php echo $a->username; ?></option>
            <?php } ?>
        </select>
        <br>
<?php 	}    
		else { ?>
        <input id="new_titolo" name="new_titolo" type="text" maxlength="250" value="<?php echo $titolo_edit; ?>" required>
        <br>
        <input id="new_sottotitolo" name="new_sottotitolo" type="text" maxlength="250" required value="<?php echo $sottotitolo_edit; ?>">
        <br>
        <textarea id="new_html_abstract" name="new_html_abstract" rows="10" cols="50" required><?php echo $htmla_edit; ?></textarea>
        <br>
        <textarea id="new_html_full" name="new_html_full" rows="10" cols="50" required><?php echo $htmlf_edit; ?></textarea>
        <br>
<?php 	}   ?>
        <button id="cancel-modifica" class="cancel-btn" href="#"	
				onclick="document.getElementById('new_autore').removeAttribute('required');"> 
         Cancel 
        </button>
        <?php 
			if ($is_new_auth) { ?>
			<input class="action-btn" type="submit" name ="update-author" value = "Confirm">
        <?php
			}
			if (!$is_immediate && !$is_new_auth){ 
				if ($is_mod) { 
					if ($id_stato_edit == 3 && !$is_cauth) { ?>
                    <input class="action-btn" type="submit" name ="update" value = "Confirm">
              <?php	}
					if ($id_stato_edit == 4 || $is_visible_edit || ($id_stato_edit == 3 && $is_cauth)) { ?>
        <input class="action-btn" type="submit" name ="update-draft" value = "Confirm and save as draft">
        <input class="action-btn darkest-btn" type="submit" name ="update-review" value = "Confirm and submit for review">
        <input type="hidden" name ="id_stato_update" value = "<?php echo $id_stato_edit; ?>">
        <input type="hidden" name ="is_cauth" value = "<?php echo $is_cauth; ?>">
        <?php		} 
                }	

			    if ($is_auth && !$is_mod) { ?>        
        <input class="action-btn" type="submit" name ="update-draft" value = "Confirm and save as draft">
        <input class="action-btn darkest-btn" type="submit" name ="update-review" value = "Confirm and submit for review">
        <input type="hidden" name ="id_stato_update" value = "<?php echo $id_stato_edit; ?>">
        <input type="hidden" name ="is_cauth" value = "<?php echo $is_cauth; ?>">
        <?php 	}
			}

			if ($is_immediate || ($is_simple && $is_mod && $id_stato_edit == 1)){ 
				if (!$is_immediate) { ?>
        <input class="action-btn" type="submit" name ="update" value = "Confirm">
        <?php	}
        		if (!$is_visible_edit) { ?>
        <input class="action-btn medium-btn" type="submit" name ="update-approve" value = "Confirm and approve">
        <?php 	} 
				$is_published2 = 1;
                $queryPub = $db->getQuery(true)
                                      ->select($db->qn('versione'))
                                      ->from($db->qn('#__contenuto'))
                                      ->where($db->qn('id_contenuto') . ' = :id_edit')
									  ->where($db->qn('versione') . ' <> :old_versione')
                                      ->where($db->qn('is_visible') . ' = :is_published2')
                                      ->bind(':id_edit', $id_edit, $int)
									  ->bind(':old_versione', $old_versione, $int)
                                      ->bind(':is_published2', $is_published2, $bool);
                $pub2 = $db->setQuery($queryPub)->loadResult();
				
				if (empty($pub2)) {
		?>
        <input class="action-btn darkest-btn" type="submit" name ="update-publish" value = "Confirm, approve and publish">
        <?php 	}
				
				else { ?>
        <input class="action-btn darkest-btn" onclick="return confirm('There is already a published version of this article!\nAre you sure you want to unpublish it and replace it with this one?\nOK = The selected version will replace the previously published version. Cancel = Nothing will change.');" type="submit" name ="update-publish" value = "Confirm, approve and publish">
        <input type="hidden" name ="old_vpub2" value = "<?php echo $pub2; ?>">
        <?php 	}
			}
		?>
        <input type="hidden" name ="is_clone" value = "<?php echo $is_clone; ?>">
        <input type="hidden" name ="is_new_from" value = "<?php echo $is_new_from; ?>">
        <input type="hidden" name ="id_update" value = "<?php echo $id_edit; ?>">
        <input type="hidden" name ="versione_update" value = "<?php echo $versione_edit; ?>">
        <input type="hidden" name ="is_visible_update" value = "<?php echo $is_visible_edit; ?>">
        <input type="hidden" name ="id_autore_update" value = "<?php echo $id_autore_edit; ?>">
        <input type="hidden" name ="id_cat_update" value = "<?php echo $id_cat_edit; ?>">
        <input type="hidden" name ="posizione_update" value = "<?php echo $posizione_edit; ?>">
        <input type="hidden" name ="filtro_update" value = "<?php echo isset($filtro)? $filtro : 'tutti-articoli'; ?>">
        <?php echo JHtml::_('form.token'); ?>
      </form>
      <div id="countdown2"></div>
      <script>
  function countdown( elementName, minutes, seconds ) {
        var element, endTime, hours, mins, msLeft, time;

        function twoDigits( n )
        {
            return (n <= 9 ? "0" + n : n);
        }
        function updateTimer()
        {
            msLeft = endTime - (+new Date);
            if ( msLeft < 1000 ) {
                element.innerHTML = "The countdown is over!";
            } else {
                time = new Date( msLeft );
                hours = time.getUTCHours();
                mins = time.getUTCMinutes();
                element.innerHTML = (hours ? hours + ':' + twoDigits( mins ) : mins) + ':' + twoDigits( time.getUTCSeconds() );
                setTimeout( updateTimer, time.getUTCMilliseconds() + 500 );
            }
        }
        element = document.getElementById( elementName );
        endTime = (+new Date) + 1000 * (60*minutes + seconds) + 500;
        updateTimer();
    }
  countdown( "countdown2", 60, 0 );      
  </script>
    </div>
    <?php }
else {
?>
      <?php 
		if (!$user->guest) { 
	?>
    <div style='display:none' id="form" class="form">
      <form action="" method="POST">
        <input id="titolo" name="titolo" type="text" placeholder="Title" maxlength="250" required>
        <br>
        <input id="sottotitolo" name="sottotitolo" type="text" placeholder="Subtitle" maxlength="250" required>
        <br>
        <textarea id="html_abstract" name="html_abstract" placeholder="Enter an abstract and/or an image" rows="10" cols="50" required></textarea>
        <br>
        <textarea id="html_full" name="html_full" placeholder="Enter HTML code or only text" rows="10" cols="50" required></textarea>
        <br>
        <button class="cancel-btn" onclick=" getElementById('form').style.display = 'none'; getElementById('parent-filtri').style.display = 'flex'; set('flex');"> 
         Cancel 
        </button>
        <input type="submit" name ="insert" class="action-btn" value = "Confirm and save as draft">
        <input type="submit" name ="insert-review" class="action-btn darkest-btn" value = "Confirm and submit for review">
        <?php echo JHtml::_('form.token'); ?>
      </form>
      <div id="countdown2"></div>
<script>
    function countdown(elementName, minutes, seconds)
{
    var element, endTime, hours, mins, msLeft, time;
	
    function twoDigits( n )
    {
        return (n <= 9 ? "0" + n : n);
    }
	
    function updateTimer()
    {
        msLeft = endTime - (+new Date);
        if ( msLeft < 1000 ) {
            element.innerHTML = "The countdown is over!";
        } else {
            time = new Date( msLeft );
            hours = time.getUTCHours();
            mins = time.getUTCMinutes();
            element.innerHTML = (hours ? hours + ':' + twoDigits( mins ) : mins) + ':' + twoDigits( time.getUTCSeconds() );
            setTimeout( updateTimer, time.getUTCMilliseconds() + 500 );
        }
    }
    element = document.getElementById( elementName );
    endTime = (+new Date) + 1000 * (60*minutes + seconds) + 500;
    updateTimer();
}

countdown( "countdown2", 60, 0 );
</script>
    </div>
<?php }  
    foreach ($contenuti as $c) {
	$is_cont_author = ($user->id == $c->id_autore);
	$is_auth = in_array(3, $user->getAuthorisedGroups());
	$is_mod = in_array(5, $user->getAuthorisedGroups());
	$tmp = "$c->html_abstract";?>
    <div class="articolo" role="article">          
      <div class="abstract-container">
       <div class="abstract-articolo">
            <?php echo $tmp; ?>
         </div>
      </div>  
      <div role="column" class="elementi-articolo">
        <a role="columnheader" aria-label="Content heading"><h1><?php echo $c->titolo; ?></h1></a>
        <a role="columnheader" aria-label="Content subheading"><h4><?php echo $c->sottotitolo; ?></h4></a>
        <?php
        if ($is_cont_author || $is_mod) {
        ?>
        <p role="columnheader" aria-label="Author">
          <?php
     $db = JFactory::getDbo();
     $query  = $db->getQuery(true)
                  ->select($db->qn('username'))
                  ->from($db->qn('#__users'))
                  ->where($db->qn('id') . ' = :id_autore')
                  ->bind(':id_autore', $c->id_autore, $int);
 	 echo ucfirst($db->setQuery($query)->loadResult());
	 if (!is_null($c->data_pubblicazione)) {
		 echo ', ' . date_format(date_create($c->data_pubblicazione), 'd-m-Y, H:i:s');
	 }
     ?>
        </p>
        <p role="columnheader" aria-label="Article info">
          <?php
		  echo "Id: " . $c->id_contenuto . ". ";
          $db = JFactory::getDbo();
          $query = $db->getQuery(true)
                      ->select('a.nome')
                      ->from($db->qn('#__stato', 'a'))
                      ->join('INNER', $db->qn('#__contenuto', 'b') . ' ON ' . $db->qn('a.id_stato') . ' = ' . 		
                                  $db->qn('b.id_stato'))
                      ->where($db->qn('b.id_contenuto') . ' = :id_contenuto')
                      ->where($db->qn('b.versione') . ' = :versione')
                      ->bind(':id_contenuto', $c->id_contenuto, $int)
                      ->bind(':versione', $c->versione, $int);

				echo ucfirst($db->setQuery($query)->loadResult());

				$query = $db->getQuery(true)
							->select($db->qn('is_visible'))
							->from($db->qn('#__contenuto'))
							->where($db->qn('id_contenuto') . ' = :id_contenuto')
                            ->where($db->qn('versione') . ' = :versione')
                            ->bind(':id_contenuto', $c->id_contenuto, $int)
                            ->bind(':versione', $c->versione, $int);
				
				$is_visible = $db->setQuery($query)->loadResult();				
				$visibility = $is_visible ? 'published' : 'hidden';				

				echo ', ' . $visibility;
			?>
        </p>
        <?php } ?>
<div class="box-read-more">
          <a class="link-read-more" href="<?php echo getArticleSlug($c); ?>"><p class="read-more" role="navigation" aria-label="Read more">
            Read more...
          </p></a>
        </div>
      </div>
        <div class="dropdown">
          <?php 
  if ($user->guest) { ?>
          <button class="dropbtn" visibility: hidden></button>
          <?php
  }
  elseif ($is_auth || $is_mod) { 
	if (isset($_POST['filtro'])) {
		$filtro = $_POST['filtro'];
	}

	elseif (isset($_POST['filtro2'])) {
		$filtro = $_POST['filtro2'];
	}

	elseif (isset($_POST['filtro_update'])) {
		$filtro = $_POST['filtro_update'];
	}

	else {
		$filtro = 'tutti-articoli';
	}
?>
          <button onclick="apriDropdown()" class="dropbtn" role="button" aria-label="Options dropdown">Options</button>
<?php } ?>
          <div class="dropdown-content">	  
            <form action="" method="POST">
<?php 
     if ($c->id_stato != 2 || (!$c->is_visible && !$is_mod)) { ?>
              <input class="option-btn" type="submit" name ="edit" value = "Edit">
              <input type="hidden" name ="is_cont_author" value = "<?php echo $is_cont_author; ?>" >
<?php
	} 
     if ($c->is_visible) { 
		if ($is_cont_author) { ?>
              <input class="option-btn" type="submit" name ="clone-edit" value = "Clone to new version">
<?php	} ?>
              <input class="option-btn" type="submit" name ="new-edit" value = "New card from this">
              <input type="hidden" name ="is_cont_author" value = "<?php echo $is_cont_author; ?>" >
<?php
	} 
	if ($is_mod) { ?>
			<input class="option-btn" type="submit" name ="author-edit" value = "Change author">
<?php		
        if ($c->id_stato == 2) { ?>
			  <input class="option-btn" type="submit" name ="immediate-edit" value = "Immediate edit">
<?php
		 }
	} 

	if ($is_mod || !$c->is_visible) {
?>
              <input class="option-btn" type="submit" name ="delete" onclick="return confirm('Are you sure you want to remove this article?\nOK = This article will be removed permanently.\nCancel = Nothing will happen.');" value = "Remove">
<?php }
    
	if ($is_mod) {
    	if ($c->id_stato == 1) {
            ?>  
              <input class="option-btn" type="submit" name ="approve" value = "Approve">
              <input class="option-btn" type="submit" name ="refute" value = "Reject">
<?php 	} 
		elseif ($c->id_stato != 4) {
        	if ($c->is_visible) { ?>
              <input class="option-btn" type="submit" name ="unpublish-review" value = "Review">
<?php	 	} 
			else { ?>  
              <input class="option-btn" type="submit" name ="review" value = "Review">
<?php 		}
     	}
      if ($c->is_visible) { ?>
              <input class="option-btn" type="submit" name ="unpublish" value = "Unpublish"> 
<?php } 

	  else { 
                      $is_published = 1;
                      $queryPub = $db->getQuery(true)
                                      ->select($db->qn('versione'))
                                      ->from($db->qn('#__contenuto'))
                                      ->where($db->qn('id_contenuto') . ' = :id_contenuto')
									  ->where($db->qn('versione') . ' <> :versione')
                                      ->where($db->qn('is_visible') . ' = :is_published')
                                      ->bind(':id_contenuto', $c->id_contenuto, $int)
									  ->bind(':versione', $c->versione, $int)
                                      ->bind(':is_published', $is_published, $bool);
                      $pub = $db->setQuery($queryPub)->loadResult();

					  if (empty($pub)) {
                	  		if ($c->id_stato == 2) { ?>
                  <input class="option-btn" type="submit" name ="publish" value = "Publish">
     <?php 			  		}
	  				  		if ($c->id_stato == 1) { ?>
                  <input class="option-btn" type="submit" name ="approve-publish" value = "Approve and publish">
     <?php  		  		}
     				  }	

					  else { ?>
              	  <input type="hidden" name ="old_vpub" value = "<?php echo $pub; ?>">
	<?php					if ($c->id_stato == 2) { ?>
                  <input class="option-btn" onclick="return confirm(alertPub);" type="submit" name ="publish" value = "Publish" >
     <?php 			  		} 
					  		if ($c->id_stato == 1) { ?>
                  <input class="option-btn" onclick="return confirm(alertPub);" type="submit" name ="approve-publish" value = "Approve and publish">
     <?php  		  		}
					  }
		}
     ?>
              <input class="option-btn" type="submit" name ="move-up" value = "Move up">
              <input class="option-btn" type="submit" name ="move-down" value = "Move down">
              <?php } ?>  
              <input type="hidden" name ="id_cont" value = "<?php echo $c->id_contenuto; ?>">
              <input type="hidden" name ="vers_cont" value = "<?php echo $c->versione; ?>">
              <input type="hidden" name ="filtro2" value = "<?php echo isset($filtro)? $filtro : 'tutti-articoli'; ?>">
              <?php echo JHtml::_( 'form.token' ); ?>
              </form>
				</div>
      </div>
      </div>
<?php } 
}
?>
  </div>
  <script>
      function set(disp)
      {
        x=document.getElementsByClassName("articolo");
        for(var i = 0; i < x.length; i++){
          x[i].style.display=disp;
        }
      }
    
      var alertPub = 'There is already a published version of this article!\nAre you sure you want to unpublish it and replace it with this one?\nOK = The selected version will replace the previously published version. Cancel = Nothing will change.';
    </script> 
  </body>
</html>
