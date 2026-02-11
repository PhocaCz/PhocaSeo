<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;
?>
<div class="card">
    <div class="card-body">
        <?php

$imgUrl = 'media/com_phocaseo/images/admin/';

echo '<div class="ph-box-info">';

echo '<div style="float:right;margin:10px;">' . HTMLHelper::_('image', $imgUrl . 'logo-phoca.png', 'Phoca.cz' ) .'</div>'
	. '<div class="ph-logo-product">'.HTMLHelper::_('image', $imgUrl . 'logo-phoca-seo.svg', 'Phoca.cz') . '</div>'
	.'<h3>Phoca SEO - '. Text::_('COM_PHOCASEO_INFORMATION').'</h3>'
	.'<div style="clear:both;"></div>';

echo '<h3>'.  Text::_('COM_PHOCASEO_HELP').'</h3>';

echo '<p>';
if (!empty($this->t['component_links'])) {
	foreach ($this->t['component_links'] as $k => $v) {
	    echo '<div><a href="'.$v[1].'" target="_blank">'.$v[0].'</a></div>';
	}
}
echo '</p>';

echo '<h3>'.  Text::_('COM_PHOCASEO_VERSION').'</h3>'
.'<p>'.  $this->t['version'] .'</p>';

echo '<h3>'.  Text::_('COM_PHOCASEO_COPYRIGHT').'</h3>'
.'<p>© 2007 - '.  date("Y"). ' Jan Pavelka</p>'
.'<p><a href="https://www.phoca.cz/" target="_blank">www.phoca.cz</a></p>';

echo '<h3>'.  Text::_('COM_PHOCASEO_LICENSE').'</h3>'
.'<p><a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPLv2</a></p>';

echo '<h3>'.  Text::_('COM_PHOCASEO_TRANSLATION').': '. Text::_('COM_PHOCASEO_TRANSLATION_LANGUAGE_TAG').'</h3>'
        .'<p>© 2007 - '.  date("Y"). ' '. Text::_('COM_PHOCASEO_TRANSLATER'). '</p>'
        .'<p>'.Text::_('COM_PHOCASEO_TRANSLATION_SUPPORT_URL').'</p>';

echo '<input type="hidden" name="task" value="" />'
.'<input type="hidden" name="option" value="com_phocaseo" />';

echo HTMLHelper::_('image', $imgUrl . 'logo.png', 'Phoca.cz');

echo '<p>&nbsp;</p>';


$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->addInlineStyle('

.upBox {
    display: flex;
    flex-wrap: wrap;
    margin-top:1em;
    margin-bottom: 2em;
}

.upItemText {
    margin-bottom: 1em;
}

.upItem {
    padding: 1em;
    text-align: center;
    width: calc(50% - 0.4em);
    margin: 0.2em;
    border-radius: 0.3em;
}

.upItemD {
    background: #F5D042;
    color: #000;
    border: 2px solid #F5D042;

}
.upItemPh {
    background: rgba(255,255,255,0.7);
    color: #000;
    border: 2px solid #000;
}
.upItemDoc {
    background: rgba(255,255,255,0.7);
    color: #000;
    border: 2px solid #000;
}
.upItemJ {
    background: rgba(255,255,255,0.7);
    color: #000;
    border: 2px solid #000;
}

a.upItemLink {
    padding: 0.5em 1em;
    border-radius: 9999px;
    margin: 1em;
    display: inline-block;
}

a.upItemLink::before {
    content: none;
}
.upItemPh a.upItemLink {
    background: #000;
    color: #fff;
}
.upItemDoc a.upItemLink {
    background: #000;
    color: #fff;
}
.upItemJ a.upItemLink {
    background: #000;
    color: #fff;
}

.phTemplateItems {
    display: flex;
    flex-wrap: wrap;
    margin-top:1em;
    margin-bottom: 2em;
}

.phTemplateItem {
    padding: 1em;
    text-align: center;
    width: calc(33% - 0.4em);
    margin: 0.2em;
    border-radius: 0.3em;
}

.phTemplateItem img{
    width: 100%;
    height: auto;
}

.phTemplateItemsInfo {
    margin: 1em auto;
}
.phTemplateItemTitle {
    font-size: small;
}
.phTemplateItem a::before {
    content: none;
}
');

$upEL = 'https://extensions.joomla.org/extension/phoca-seo/';
$upE = 'Phoca Cart';

$o = '<div class="upBox">';

$o .=  '<div class="upItem upItemD">';
$o .=  '<div class="upItemText">'.Text::_('COM_PHOCASEO_ADMIN_PROJECT_INFO1'). '</div>';
$o .=  '<form action="https://www.paypal.com/donate" method="post" target="_top">';
$o .=  '<input type="hidden" name="hosted_button_id" value="ZVPH25SQ2DDBY" />';
$o .=  '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />';
$o .=  '<img alt="" border="0" src="https://www.paypal.com/en_CZ/i/scr/pixel.gif" width="1" height="1" />';
$o .=  '</form>';
$o .=  '</div>';

$o .=  '<div class="upItem upItemJ">';
$o .=  '<div class="upItemText">'.Text::_('COM_PHOCASEO_ADMIN_PROJECT_INFO2'). '</div>';
$o .=  '<a class="upItemLink" target="_blank" href="'. $upEL.'">'. $upE.' (JED '.Text::_('COM_PHOCASEO_WEBSITE').')</a>';
$o .=  '</form>';
$o .=  '</div>';

$o .=  '<div class="upItem upItemDoc">';
$o .=  '<div class="upItemText">'.Text::_('COM_PHOCASEO_ADMIN_PROJECT_INFO3'). '</div>';
$o .=  '<a class="upItemLink" target="_blank" href="https://www.phoca.cz/documentation">Phoca documentation '.Text::_('COM_PHOCASEO_WEBSITE').'</a>';
$o .=  '<div class="upItemText">'.Text::_('COM_PHOCASEO_ADMIN_PROJECT_INFO5'). '</div>';
$o .=  '<a class="upItemLink" target="_blank" href="https://www.phoca.cz/forum">Phoca forum '.Text::_('COM_PHOCASEO_WEBSITE').'</a>';
$o .=  '</div>';

$o .=  '<div class="upItem upItemPh">';
$o .=  '<div class="upItemText">'.Text::_('COM_PHOCASEO_ADMIN_PROJECT_INFO4'). '</div>';
$o .=  '<a class="upItemLink" target="_blank" href="https://www.phoca.cz">Phoca '.Text::_('COM_PHOCASEO_WEBSITE').'</a>';
$o .=  '</div>';

$o .=  '</div>';





echo $o;


echo '<div class="ph-cp-hr"></div>';

echo '<div class="btn-group">';

echo '<a class="btn btn-large btn-primary ph-cp-btn-update" href="https://www.phoca.cz/version/index.php?phocaseo='.  $this->t['version'] .'" target="_blank"><i class="icon-loop icon-white"></i>&nbsp;&nbsp;'.  Text::_('COM_PHOCASEO_CHECK_FOR_UPDATE') .'</a></div>';


echo '<div class="clearfix"></div>';

echo '<div style="margin-top:30px;height:39px;background: url(\''.Uri::root(true).'/media/com_phocaseo/images/admin/line.png\') 100% 0 no-repeat;">&nbsp;</div>';

echo '</div>';


echo '</div>';



        ?>
    </div>
</div>


