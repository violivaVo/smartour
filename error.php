<?php 
/**
 * @package     Joomla.Site
 * @subpackage  Templates.cassiopeia
 *
 * @copyright   (C) 2017 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var Joomla\CMS\Document\ErrorDocument $this */

    $user = Factory::getUser();
    $app = Factory::getApplication();
    $wa  = $this->getWebAssetManager();

    // Browsers support SVG favicons
    $this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
    $this->addHeadLink(HTMLHelper::_('image', 'favicon.ico', '', [], true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);
    $this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon-pinned.svg', '', [], true, 1), 'mask-icon', 'rel', ['color' => '#000']);

    // Detecting Active Variables
    $option   = $app->input->getCmd('option', '');
    $view     = $app->input->getCmd('view', '');
    $layout   = $app->input->getCmd('layout', '');
    $task     = $app->input->getCmd('task', '');
    $itemid   = $app->input->getCmd('Itemid', '');
    $sitename = htmlspecialchars($app->get('sitename'), ENT_QUOTES, 'UTF-8');
    $menu     = $app->getMenu()->getActive();
    $pageclass = $menu !== null ? $menu->getParams()->get('pageclass_sfx', '') : '';

    // Color Theme
    $paramsColorName = $this->params->get('colorName', 'colors_standard');
    $assetColorName  = 'theme.' . $paramsColorName;
    $wa->registerAndUseStyle($assetColorName, 'media/templates/site/cassiopeia/css/global/' . $paramsColorName . '.css');

    // Use a font scheme if set in the template style options
    $paramsFontScheme = $this->params->get('useFontScheme', false);
    $fontStyles       = '';

    if ($paramsFontScheme)
    {
        if (stripos($paramsFontScheme, 'https://') === 0)
        {
            $this->getPreloadManager()->preconnect('https://fonts.googleapis.com/', ['crossorigin' => 'anonymous']);
            $this->getPreloadManager()->preconnect('https://fonts.gstatic.com/', ['crossorigin' => 'anonymous']);
            $this->getPreloadManager()->preload($paramsFontScheme, ['as' => 'style', 'crossorigin' => 'anonymous']);
            $wa->registerAndUseStyle('fontscheme.current', $paramsFontScheme, [], ['media' => 'print', 'rel' => 'lazy-stylesheet', 'onload' => 'this.media=\'all\'', 'crossorigin' => 'anonymous']);

            if (preg_match_all('/family=([^?:]*):/i', $paramsFontScheme, $matches) > 0)
            {
                $fontStyles = '--cassiopeia-font-family-body: "' . str_replace('+', ' ', $matches[1][0]) . '", sans-serif;
                --cassiopeia-font-family-headings: "' . str_replace('+', ' ', isset($matches[1][1]) ? $matches[1][1] : $matches[1][0]) . '", sans-serif;
                --cassiopeia-font-weight-normal: 400;
                --cassiopeia-font-weight-headings: 700;';
            }
        }
        else
        {
            $wa->registerAndUseStyle('fontscheme.current', $paramsFontScheme, ['version' => 'auto'], ['media' => 'print', 'rel' => 'lazy-stylesheet', 'onload' => 'this.media=\'all\'']);
            $this->getPreloadManager()->preload($wa->getAsset('style', 'fontscheme.current')->getUri() . '?' . $this->getMediaVersion(), ['as' => 'style']);
        }
    }

    // Enable assets
    $wa->usePreset('template.cassiopeia.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr'))
        ->useStyle('template.active.language')
        ->useStyle('template.user')
        ->useScript('template.user')
        ->addInlineStyle(":root {
            --hue: 214;
            --template-bg-light: #f0f4fb;
            --template-text-dark: #495057;
            --template-text-light: #ffffff;
            --template-link-color: #2a69b8;
            --template-special-color: #001B4C;
            $fontStyles
        }");

    // Override 'template.active' asset to set correct ltr/rtl dependency
    $wa->registerStyle('template.active', '', [], [], ['template.cassiopeia.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr')]);

    // Logo file or site title param
    if ($this->params->get('logoFile'))
    {
        $logo = '<img src="' . Uri::root(true) . '/' . htmlspecialchars($this->params->get('logoFile'), ENT_QUOTES) . '" alt="' . $sitename . '">';
    }
    elseif ($this->params->get('siteTitle'))
    {
        $logo = '<span title="' . $sitename . '">' . htmlspecialchars($this->params->get('siteTitle'), ENT_COMPAT, 'UTF-8') . '</span>';
    }
    else
    {
        $logo = HTMLHelper::_('image', 'logo.svg', $sitename, ['class' => 'logo d-inline-block'], true, 0);
    }

    $hasClass = '';

    if ($this->countModules('sidebar-left', true))
    {
        $hasClass .= ' has-sidebar-left';
    }

    if ($this->countModules('sidebar-right', true))
    {
        $hasClass .= ' has-sidebar-right';
    }

    // Container
    $wrapper = $this->params->get('fluidContainer') ? 'wrapper-fluid' : 'wrapper-static';

    $this->setMetaData('viewport', 'width=device-width, initial-scale=1');

    $stickyHeader = $this->params->get('stickyHeader') ? 'position-sticky sticky-top' : '';

    // Defer font awesome
    $wa->getAsset('style', 'fontawesome')->setAttribute('rel', 'lazy-stylesheet');
	
    if (($this->error->getCode()) == '404') {
        $url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $prefix = "https://www\.smartour\.net/";
        $sections = array("work-packages", "news", "partners", "publications");
        $is_article = 0;
        $is_version = 0;
        foreach ($sections as $s) {

            if (preg_match("#". $prefix . "(index\.php/){0,1}" . $s . "/[1-9][0-9]*-v[1-9][0-9]*$#", $url) ) {
                $is_article = 1;
                $is_version = 1;
                break;
            }

            if (preg_match("#". $prefix . "(index\.php/){0,1}" . $s . "/[1-9][0-9]*$#", $url)) {
              $is_article = 1;
              break;
            }
        }

              ?>
                

<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
<head>
	<jdoc:include type="metas" />
	<jdoc:include type="styles" />
	<jdoc:include type="scripts" />
  	
  	<style>
	
    #login-link, #logout-link, #help-link {
      color: #ca6702; 
      text-decoration: none;
  	}
  
    #login-link:hover {
      color: #173a65;
  	}  
      
    #logout-link:hover {
      color: #173a65;
  	}  
      
  	#help-link:hover {
      color: #173a65;
  	} 
      
      .articolo {
        display: flex;
        flex-direction: column;
		justify-content: center;
  		align-items: center;
        padding-right: 15vw;
        padding-left:15vw;
      
      }
      
    .articolo  a {color: #ca6702;}
	.articolo a:link { text-decoration: none; }
	.articolo a:visited { text-decoration: none; }
	.articolo a:hover { text-decoration: none; color: #1d3557; }
	.articolo a:active { text-decoration: none; }
      
  	</style>
</head>

<body class="site <?php echo $option
	. ' ' . $wrapper
	. ' view-' . $view
	. ($layout ? ' layout-' . $layout : ' no-layout')
	. ($task ? ' task-' . $task : ' no-task')
	. ($itemid ? ' itemid-' . $itemid : '')
	. ($pageclass ? ' ' . $pageclass : '')
	. $hasClass
	. ($this->direction == 'rtl' ? ' rtl' : '');
?>">
	<header class="header container-header full-width<?php echo $stickyHeader ? ' ' . $stickyHeader : ''; ?>">

		<?php if ($this->countModules('topbar')) : ?>
			<div class="container-topbar">
			<jdoc:include type="modules" name="topbar" style="none" />
			</div>
		<?php endif; ?>

		<?php if ($this->countModules('below-top')) : ?>
			<div class="grid-child container-below-top">
				<jdoc:include type="modules" name="below-top" style="none" />
			</div>
		<?php endif; ?>

		<?php if ($this->params->get('brand', 1)) : ?>
			<div class="grid-child">
				<div class="navbar-brand">
					<a class="brand-logo" href="<?php echo $this->baseurl; ?>/">
						<?php echo $logo; ?>
					</a>
					<?php if ($this->params->get('siteDescription')) : ?>
						<div class="site-description"><?php echo htmlspecialchars($this->params->get('siteDescription')); ?></div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($this->countModules('menu', true) || $this->countModules('search', true)) : ?>
			<div class="grid-child container-nav">
				<?php if ($this->countModules('menu', true)) : ?>
					<jdoc:include type="modules" name="menu" style="none" />
				<?php endif; ?>
				<?php if ($this->countModules('search', true)) : ?>
					<div class="container-search">
						<jdoc:include type="modules" name="search" style="none" />
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<div class="site-grid">
		<?php if ($this->countModules('banner', true)) : ?>
			<div class="container-banner full-width">
				<jdoc:include type="modules" name="banner" style="none" />
			</div>
		<?php endif; ?>

		<?php if ($this->countModules('top-a', true)) : ?>
		<div class="grid-child container-top-a">
			<jdoc:include type="modules" name="top-a" style="card" />
		</div>
		<?php endif; ?>

		<?php if ($this->countModules('top-b', true)) : ?>
		<div class="grid-child container-top-b">
			<jdoc:include type="modules" name="top-b" style="card" />
		</div>
		<?php endif; ?>

		<?php if ($this->countModules('sidebar-left', true)) : ?>
		<div class="grid-child container-sidebar-left">
			<jdoc:include type="modules" name="sidebar-left" style="card" />
		</div>
		<?php endif; ?>

		<div class="grid-child container-component">
			<jdoc:include type="modules" name="breadcrumbs" style="none" />
			<jdoc:include type="modules" name="main-top" style="card" />
			<jdoc:include type="message" />
			<main>
			<jdoc:include type="component" />
			</main>
			<jdoc:include type="modules" name="main-bottom" style="card" />
		</div>

		<?php if ($this->countModules('sidebar-right', true)) : ?>
		<div class="grid-child container-sidebar-right">
			<jdoc:include type="modules" name="sidebar-right" style="card" />
		</div>
		<?php endif; ?>

		<?php if ($this->countModules('bottom-a', true)) : ?>
		<div class="grid-child container-bottom-a">
			<jdoc:include type="modules" name="bottom-a" style="card" />
		</div>
		<?php endif; ?>

		<?php if ($this->countModules('bottom-b', true)) : ?>
		<div class="grid-child container-bottom-b">
			<jdoc:include type="modules" name="bottom-b" style="card" />
		</div>
		<?php endif; ?>
	</div>

	<?php if ($this->countModules('footer', true)) : ?> 
	<footer class="container-footer footer full-width">
		<div class="grid-child">
			<jdoc:include type="modules" name="footer" style="none" />
		</div>
	</footer>
	<?php endif; ?>

	<?php if ($this->params->get('backTop') == 1) : ?>
		<a href="#top" id="back-top" class="back-to-top-link" aria-label="<?php echo Text::_('TPL_CASSIOPEIA_BACKTOTOP'); ?>">
			<span class="icon-arrow-up icon-fw" aria-hidden="true"></span>
		</a>
	<?php endif; ?>
  
  <div class = "articolo">
                  <?php 

                  //l'url è corretto fino a index.php e poi no, oppure fino a .net e poi no
                  if (!$is_article) {
                    header('Location: https://www.smartour.net/404');
					exit;
                  }

                  else {
                          $poslash = 1 + strrpos($url, "/");
                          $suffix = substr($url, $poslash);
                          $posdash = strpos($suffix, "-");

                          $id_contenuto = empty($posdash)? $suffix : (int) substr($suffix, 0, $posdash);
                          $posv = 1 + strpos($suffix, "v");

                          $columns = array('titolo', 'html_full');

                          $db = JFactory::getDBO();
                          $query = $db->getQuery(true)
                            ->select($db->quoteName($columns))
                            ->from($db->quoteName('#__contenuto'))
                            ->where($db->quoteName('id_contenuto') . ' = :id_contenuto')
                            ->bind(':id_contenuto', $id_contenuto, Joomla\Database\ParameterType::INTEGER);

                          if ($is_version) {
                            $versione = (int) substr($suffix, $posv);

                            $midQuery = $db->getQuery(true)
                                          ->select(array($db->qn('id_autore'), $db->qn('is_visible')))
                                          ->from($db->quoteName('#__contenuto'))
                                          ->where($db->quoteName('id_contenuto') . ' = :id_contenuto')
                                          ->where($db->quoteName('versione') . ' = :versione')
                                          ->bind(':id_contenuto', $id_contenuto, Joomla\Database\ParameterType::INTEGER)
                                          ->bind(':versione', $versione, Joomla\Database\ParameterType::INTEGER);

                            $info = $db->setQuery($midQuery)->loadObject();
                            $is_vis = $info->is_visible;
                            $id_autore = $info->id_autore;

                            if ($is_vis || $user->id == $id_autore || in_array(5, $user->getAuthorisedGroups())) {
                                $query->where($db->quoteName('versione') . ' = :versione')
                                  ->bind(':versione', $versione, Joomla\Database\ParameterType::INTEGER);
                            }

                            else {
                              // la versione individuata dall'URL non è pubblica e chi sta tentando di accedervi
                              // o non ha effettuato l'accesso o lo ha fatto, ma non è né l'autore dell'articolo,
                              // né un moderatore
                              	if ($info) {
                                  	header('Location: https://www.smartour.net/not-public');
                                }
								
								//la versione individuata dall'URL non esiste
								else {
                                  	header('Location: https://www.smartour.net/404');
                                }
                                
                              	exit;
                            }
                          }

                          else {
                            $is_visible = 1;
                            $query->where($db->quoteName('is_visible') . ' = :is_visible')
                                  ->bind(':is_visible', $is_visible, Joomla\Database\ParameterType::BOOLEAN);
                          }

                         list($titolo, $html_full) = $db->setQuery($query)->loadRow();

                          if ($titolo != "") { 
    						//rimuoviamo dal log l'errore 404
    					  ?>
    						<script> console.clear(); </script>  
                            <?php
                            $this->setTitle($titolo);
                            echo $html_full;
                          }

                          else {
                            header('Location: https://www.smartour.net/404');
							exit;
                          }


                        }
                }

                elseif (($this->error->getCode()) == '401') {
                  	  header('Location: https://www.smartour.net/auth-error');
					  exit;
                }

                elseif (($this->error->getCode()) == '403') {
                      header('Location: https://www.smartour.net/auth-error');
					  exit;
                }

                else {
                  	  header('Location: https://www.smartour.net/error');
					  exit;
                }		
          				 
              ?>
			</div>
 <jdoc:include type="modules" name="debug" style="none" />
</body>

<footer id="page-footer" class="py-3">
    <div class="footer-container">
    	<div class="logininfo">
        	<?php if ($user->guest) : ?>
         	User offline. <a id="login-link" href="https://www.smartour.net/login">Login</a>
      		<?php else: ?>
          	Logged-in user: <?php echo $user->username; ?>. 
          	<?php 
          	$userToken = JSession::getFormToken();
    		echo '<a id="logout-link" href="https://www.smartour.net/index.php?option=com_users&task=user.logout&' . $userToken . '=1">Logout' . '</a>';
          	endif; ?>
      	</div>
      	<?php if (!$user->guest) : ?>
      	<div class="help"><a id="help-link" href="https://www.smartour.net/help">Help</a></div>
      	<?php endif; ?>
	</div>
</footer>
  
</html>
