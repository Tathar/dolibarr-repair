<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin         <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2010-2011 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2011      Philippe Grand        <philippe.grand@atoo-net.com>
 * Copyright (C) 2012      Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2013      Pierre-Emmanuel DOUET	<tathar.dev@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/repair/fiche.php
 *	\ingroup    repair
 *	\brief      Page to show customer order
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formorder.class.php");
require_once(DOL_DOCUMENT_ROOT."/repair/core/modules/repair/modules_repair.php");
require_once(DOL_DOCUMENT_ROOT.'/repair/class/repair.class.php');
require_once(DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT."/repair/lib/repair.lib.php");
require_once(DOL_DOCUMENT_ROOT."/repair/class/html.formrepair.class.php");
require_once(DOL_DOCUMENT_ROOT."/repair/class/html.formmachine.class.php");
if ($conf->projet->enabled) require_once(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php');
if ($conf->projet->enabled) require_once(DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php');
if ($conf->propal->enabled) require_once(DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php'); 

if (!$user->rights->repair->read) accessforbidden();

//$langs->load('orders'); //TODO a supprimé
$langs->load('sendings');
$langs->load('companies');
$langs->load('bills');
$langs->load('propal');
$langs->load('deliveries');
$langs->load('products');
$langs->load('repairlang@repair');

$id      = (GETPOST('id','int')?GETPOST('id','int'):GETPOST("orderid"));
$ref     = GETPOST('ref');
$socid   = GETPOST('socid','int');
$action  = GETPOST('action');
$confirm = GETPOST('confirm');
$lineid  = GETPOST('lineid');
$mesg    = GETPOST('mesg');

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'repair',$id,'');

$object = new Repair($db);

// Load object
if ($id > 0 || ! empty($ref))
{
	$ret=$object->fetch($id, $ref);
}

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('repaircard'));


function pdf_create_card()
{
	if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
    {
		global $langs;
		global $conf;
		global $db;
		global $object;
		global $hookmanager;
		
    	// Define output language
     	$outputlangs = $langs;
	  	$newlang=GETPOST('lang_id','alpha');
	 	if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
		if (! empty($newlang))
	 	{
	   		$outputlangs = new Translate("",$conf);
	    	$outputlangs->setDefaultLang($newlang);
		}
	repair_pdf_create($db, $object, "RepairLabel", $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
	}
}
/******************************************************************************/
/*                     Actions                                                */
/******************************************************************************/

$parameters=array('socid'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks

// Action clone object
if ($action == 'confirm_clone' && $confirm == 'yes')
{
    if (1==0 && ! GETPOST('clone_content') && ! GETPOST('clone_receivers'))
    {
        $mesg='<div class="error">'.$langs->trans("NoCloneOptionsSpecified").'</div>';
    }
    else
    {
    	if ($object->fetch($id) > 0)
    	{
    		$result=$object->createFromClone($socid, $hookmanager);
    		if ($result > 0)
    		{
    			header("Location: ".$_SERVER['PHP_SELF'].'?id='.$result);
    			exit;
    		}
    		else
    		{
    			$mesg='<div class="error">'.$object->error.'</div>';
    			$action='';
    		}
    	}
    }
}

// Reopen a closed order
else if ($action == 'reopen' && ($user->rights->repair->ValidateRepair || $user->rights->repair->ValidateReplies))
{
    $object->fetch($id);
    if ($object->repair_statut == 8)
    {
        $result = $object->set_reopen($user);
		if ($result < 0) $mesgs=$object->errors;
    }
	else if ($object->repair_statut == -1)
    {
        $result = $object->reopen_canceledRepair($user);	
		if ($result < 0) $mesgs=$object->errors;
    }
	else if ($object->repair_statut == -2)
    {
        $result = $object->reopen_unvalidedEstimate($user);	
		if ($result < 0) $mesgs=$object->errors;
    }
}

// Suppression de la reparation
else if ($action == 'confirm_delete' && $confirm == 'yes')
{
    if ($user->rights->repair->supprimer)
    {
        $object->fetch($id);
        $object->fetch_thirdparty();
        $result=$object->delete($user);
        if ($result > 0)
        {
            Header('Location: index.php');
            exit;
        }
        else
        {
            $mesg='<div class="error">'.$object->error.'</div>';
        }
    }
}

// Remove a product line
else if ($action == 'confirm_deleteline' && $confirm == 'yes')
{
    if (($user->rights->repair->CreateEstimate) || ($user->rights->repair->MakeRepair) )
    {
        $object->fetch($id);
        $object->fetch_thirdparty();

        $result = $object->deleteline($lineid);
        if ($result > 0)
        {
            // Define output language
            $outputlangs = $langs;
            $newlang='';
            if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
            if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
            if (! empty($newlang))
            {
                $outputlangs = new Translate("",$conf);
                $outputlangs->setDefaultLang($newlang);
            }
            if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
            {
                $ret=$object->fetch($id);    // Reload to get new records
                repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
            }
        }
        else
        {
            $mesg='<div class="error">'.$object->error.'</div>';
        }
    }
    Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
    exit;
}

// Categorisation dans projet
else if ($action == 'classin')
{
    $object->fetch($id);
    $object->setProject($_POST['projectid']);
}

// Generation des fiches Machines
else if ($action == 'generatecard')
{                   	// Define output language
	$outputlangs = $langs;
	$newlang=GETPOST('lang_id','alpha');
	if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
	if (! empty($newlang))
	{
		$outputlangs = new Translate("",$conf);
		$outputlangs->setDefaultLang($newlang);
	}
	repair_pdf_create($db, $object, "RepairLabel", $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
}

// Add order
else if ($action == 'add' && $user->rights->repair->creer)
{
    $daterepair  = dol_mktime(12, 0, 0, $_POST['remonth'],  $_POST['reday'],  $_POST['reyear']);
    $datelivraison = dol_mktime(12, 0, 0, $_POST['liv_month'],$_POST['liv_day'],$_POST['liv_year']);

    $object->socid=GETPOST('socid','int');
    $object->fetch_thirdparty();

    $db->begin();

    $object->date_repair          = $daterepair;
    $object->note                 = $_POST['note'];
    $object->note_public          = $_POST['note_public'];
    $object->source               = $_POST['source_id'];
    $object->fk_project           = $_POST['projectid'];
    $object->ref_client           = $_POST['ref_client'];
    $object->modelpdf             = $_POST['model_pdf'];
    $object->cond_reglement_id    = $_POST['cond_reglement_id'];
    $object->mode_reglement_id    = $_POST['mode_reglement_id'];
    $object->availability_id      = $_POST['availability_id'];
    $object->demand_reason_id     = $_POST['demand_reason_id'];
    $object->date_livraison       = $datelivraison;
    $object->fk_delivery_address  = $_POST['fk_address'];
    $object->contactid            = $_POST['contactidp'];
//<Tathar>
	$object->trademark            = $_POST['trademark'];
	$object->support_id            = $_POST['support_id'];
    $object->model            	  = $_POST['model'];
	$object->n_model              = $_POST['n_model'];
	$object->serial_num              = $_POST['serial_num'];
	$object->breakdown              = $_POST['breakdown'];
	$object->accessory              = $_POST['accessory'];
//</Tathar>

    // If creation from another object of another module (Example: origin=propal, originid=1)
    if ($_POST['origin'] && $_POST['originid'])
    {
        // Parse element/subelement (ex: project_task)
        $element = $subelement = $_POST['origin'];
        if (preg_match('/^([^_]+)_([^_]+)/i',$_POST['origin'],$regs))
        {
            $element = $regs[1];
            $subelement = $regs[2];
        }

        // For compatibility
        if ($element == 'order')    { $element = $subelement = 'repair'; }
        if ($element == 'propal')   { $element = 'comm/propal'; $subelement = 'propal'; }
        if ($element == 'contract') { $element = $subelement = 'contrat'; }

        $object->origin    = $_POST['origin'];
        $object->origin_id = $_POST['originid'];

        // Possibility to add external linked objects with hooks
        $object->linked_objects[$object->origin] = $object->origin_id;
        if (is_array($_POST['other_linked_objects']) && ! empty($_POST['other_linked_objects']))
        {
        	$object->linked_objects = array_merge($object->linked_objects, $_POST['other_linked_objects']);
        }

        $object_id = $object->create($user);

        if ($object_id > 0)
        {
            dol_include_once('/'.$element.'/class/'.$subelement.'.class.php');

            $classname = ucfirst($subelement);
            $srcobject = new $classname($db);

            dol_syslog("Try to find source object origin=".$object->origin." originid=".$object->origin_id." to add lines");
            $result=$srcobject->fetch($object->origin_id);
            if ($result > 0)
            {
                $lines = $srcobject->lines;
                if (empty($lines) && method_exists($srcobject,'fetch_lines'))  $lines = $srcobject->fetch_lines();

                $fk_parent_line=0;
                $num=count($lines);

                for ($i=0;$i<$num;$i++)
                {
                    $desc=($lines[$i]->desc?$lines[$i]->desc:$lines[$i]->libelle);
                    $product_type=($lines[$i]->product_type?$lines[$i]->product_type:0);

                    // Dates
                    // TODO mutualiser
                    $date_start=$lines[$i]->date_debut_prevue;
                    if ($lines[$i]->date_debut_reel) $date_start=$lines[$i]->date_debut_reel;
                    if ($lines[$i]->date_start) $date_start=$lines[$i]->date_start;
                    $date_end=$lines[$i]->date_fin_prevue;
                    if ($lines[$i]->date_fin_reel) $date_end=$lines[$i]->date_fin_reel;
                    if ($lines[$i]->date_end) $date_end=$lines[$i]->date_end;

                    // Reset fk_parent_line for no child products and special product
                    if (($lines[$i]->product_type != 9 && empty($lines[$i]->fk_parent_line)) || $lines[$i]->product_type == 9) {
                        $fk_parent_line = 0;
                    }

                    $result = $object->addline(
                        $object_id,
                        $desc,
                        $lines[$i]->subprice,
                        $lines[$i]->qty,
                        $lines[$i]->tva_tx,
                        $lines[$i]->localtax1_tx,
                        $lines[$i]->localtax2_tx,
                        $lines[$i]->fk_product,
                        $lines[$i]->remise_percent,
                        $lines[$i]->info_bits,
                        $lines[$i]->fk_remise_except,
                        'HT',
                        0,
                        $datestart,
                        $dateend,
                        $product_type,
                        $lines[$i]->rang,
                        $lines[$i]->special_code,
                        $fk_parent_line
                    );

                    if ($result < 0)
                    {
                        $error++;
                        break;
                    }

                    // Defined the new fk_parent_line
                    if ($result > 0 && $lines[$i]->product_type == 9) {
                        $fk_parent_line = $result;
                    }
                }

                // Hooks
                $parameters=array('objFrom'=>$srcobject);
                $reshook=$hookmanager->executeHooks('createFrom',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
                if ($reshook < 0) $error++;
            }
            else
            {
                $mesg=$srcobject->error;
                $error++;
            }
        }
        else
        {
            $mesg=$object->error;
            $error++;
        }
    }
    else
    {
        $object_id = $object->create($user);

        // If some invoice's lines already known
        $NBLINES=8;
        for ($i = 1 ; $i <= $NBLINES ; $i++)
        {
            if ($_POST['idprod'.$i])
            {
                $xid = 'idprod'.$i;
                $xqty = 'qty'.$i;
                $xremise = 'remise_percent'.$i;
                $object->add_product($_POST[$xid],$_POST[$xqty],$_POST[$xremise]);
            }
        }

		if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
        {
        	// Define output language
         	$outputlangs = $langs;
          	$newlang=GETPOST('lang_id','alpha');
         	if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
        	if (! empty($newlang))
         	{
            	$outputlangs = new Translate("",$conf);
            	$outputlangs->setDefaultLang($newlang);
      		}

//		$ret=$object->fetch($id);    // Reload to get new records
//		repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
		repair_pdf_create($db, $object, "RepairLabel", $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
		}
    }

    // Insert default contacts if defined
    if ($object_id > 0)
    {
        if ($_POST["contactidp"])
        {
            $result=$object->add_contact($_POST["contactidp"],'CUSTOMER','external');

            if ($result < 0)
            {
                $mesg = '<div class="error">'.$langs->trans("ErrorFailedToAddContact").'</div>';
                $error++;
            }
        }

        $id = $object_id;
        $action = '';
    }

    // End of object creation, we show it
    if ($object_id > 0 && ! $error)
    {
        $db->commit();
        Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object_id);
        exit;
    }
    else
    {
        $db->rollback();
        $action='create';
        $socid=$_POST['socid'];
        if (! $mesg) $mesg='<div class="error">'.$object->error.'</div>';
    }

}

// Positionne ref reparation client
else if ($action == 'set_ref_client' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_ref_client($user, $_POST['ref_client']);
	pdf_create_card();
}

// Positionne la prise en charge
else if ($action == 'set_support_id' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_support_id($user, $_POST['support_id']);
	pdf_create_card();
}

// Positionne la marque de la machine
else if ($action == 'set_trademark' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_trademark($user, $_POST['trademark']);
	pdf_create_card();
}

// Positionne le N° de modele de la machine
else if ($action == 'set_n_model' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_n_model($user, $_POST['n_model']);
	pdf_create_card();
}

// Positionne le Type de la machine
else if ($action == 'set_type' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_type_id($user, $_POST['type_id']);
	pdf_create_card();
}

// Positionne le Modele de la machine
else if ($action == 'set_model' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_model($user, $_POST['model']);
	pdf_create_card();
}

// Positionne le N° de serie de la machine
else if ($action == 'set_serial_num' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_serial_num($user, $_POST['serial_num']);
	pdf_create_card();
}

// Positionne la panne de la machine
else if ($action == 'set_breakdown' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_breakdown($user, $_POST['breakdown']);
	pdf_create_card();
}

// Positionne les accessoires de la machine
else if ($action == 'set_accessory' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_accessory($user, $_POST['accessory']);
	pdf_create_card();
}

else if ($action == 'setremise' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->set_remise($user, $_POST['remise']);
}

else if ($action == 'setabsolutediscount' && $user->rights->repair->creer)
{
    if ($_POST["remise_id"])
    {
        $ret=$object->fetch($id);
        if ($ret > 0)
        {
            $object->insert_discount($_POST["remise_id"]);
        }
        else
        {
            dol_print_error($db,$object->error);
        }
    }
}

else if ($action == 'setdate' && $user->rights->repair->creer)
{
    //print "x ".$_POST['liv_month'].", ".$_POST['liv_day'].", ".$_POST['liv_year'];
    $date=dol_mktime(0, 0, 0, $_POST['order_month'], $_POST['order_day'], $_POST['order_year']);

    $object->fetch($id);
    $result=$object->set_date($user,$date);
    if ($result < 0)
    {
        $mesg='<div class="error">'.$object->error.'</div>';
    }
	pdf_create_card();
}

else if ($action == 'setdate_livraison' && $user->rights->repair->creer)
{
    //print "x ".$_POST['liv_month'].", ".$_POST['liv_day'].", ".$_POST['liv_year'];
    $datelivraison=dol_mktime(0, 0, 0, $_POST['liv_month'], $_POST['liv_day'], $_POST['liv_year']);

    $object->fetch($id);
    $result=$object->set_date_livraison($user,$datelivraison);
    if ($result < 0)
    {
        $mesg='<div class="error">'.$object->error.'</div>';
    }
	pdf_create_card();
}

else if ($action == 'setmode' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result = $object->setPaymentMethods(GETPOST('mode_reglement_id','int'));
    if ($result < 0) dol_print_error($db,$object->error);
}

else if ($action == 'setavailability' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result=$object->availability($_POST['availability_id']);
    if ($result < 0) dol_print_error($db,$object->error);
}

else if ($action == 'setdemandreason' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result=$object->demand_reason($_POST['demand_reason_id']);
    if ($result < 0) dol_print_error($db,$object->error);
}

else if ($action == 'setconditions' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result=$object->setPaymentTerms(GETPOST('cond_reglement_id','int'));
    if ($result < 0)
    {
    	dol_print_error($db,$object->error);
    }
    else
	{
		if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
        {
        	// Define output language
        	$outputlangs = $langs;
        	$newlang=GETPOST('lang_id','alpha');
        	if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
        	if (! empty($newlang))
        	{
        		$outputlangs = new Translate("",$conf);
        		$outputlangs->setDefaultLang($newlang);
        	}

            $ret=$object->fetch($id);    // Reload to get new records
            repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
        }
    }
}

else if ($action == 'setremisepercent' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result = $object->set_remise($user, $_POST['remise_percent']);
}

else if ($action == 'setremiseabsolue' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $result = $object->set_remise_absolue($user, $_POST['remise_absolue']);
}

else if ($action == 'setnote_public' && $user->rights->repair->creer)
{
	$object->fetch($id);
	$result=$object->update_note_public(dol_html_entity_decode(GETPOST('note_public'), ENT_QUOTES));
	if ($result < 0) dol_print_error($db,$object->error);
	pdf_create_card();
}

else if ($action == 'setnote' && $user->rights->repair->creer)
{
	$object->fetch($id);
	$result=$object->update_note(dol_html_entity_decode(GETPOST('note'), ENT_QUOTES));
	if ($result < 0) dol_print_error($db,$object->error);
}

/*
 *  Ajout d'une ligne produit dans la reparation
 */
else if ($action == 'addline' && (($user->rights->repair->CreateEstimate) || ($user->rights->repair->MakeRepair)) )
{
	$langs->load('errors');
	$error = false;

	$idprod=GETPOST('idprod', 'int');
	$product_desc = (GETPOST('product_desc')?GETPOST('product_desc'):(GETPOST('np_desc')?GETPOST('np_desc'):(GETPOST('dp_desc')?GETPOST('dp_desc'):'')));
	$price_ht = GETPOST('price_ht');
	$tva_tx = (GETPOST('tva_tx')?GETPOST('tva_tx'):0);

	if ((empty($idprod) || GETPOST('usenewaddlineform')) && ($price_ht < 0) && (GETPOST('qty') < 0))
    {
        setEventMessage($langs->trans('ErrorBothFieldCantBeNegative', $langs->transnoentitiesnoconv('UnitPriceHT'), $langs->transnoentitiesnoconv('Qty')), 'errors');
        $error = true;
    }
	if (empty($idprod) && GETPOST('type') < 0)
	{
		setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), 'errors');
        $error = true;
	}
	if ((empty($idprod) || GETPOST('usenewaddlineform')) && (!($price_ht >= 0) || $price_ht == ''))	// Unit price can be 0 but not ''
	{
		setEventMessage($langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("UnitPriceHT")), 'errors');
		$error++;
	}
	if (! GETPOST('qty') && GETPOST('qty') == '')
    {
        setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Qty')), 'errors');
        $error = true;
    }
    if (empty($idprod) && empty($product_desc))
    {
        setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Description')), 'errors');
        $error = true;
    }

	if (! $error && (GETPOST('qty') >= 0) && (! empty($product_desc) || ! empty($idprod)))
	{
		// Clean parameters
		$predef=((! empty($idprod) && $conf->global->MAIN_FEATURES_LEVEL < 2) ? '_predef' : '');
		$date_start=dol_mktime(0, 0, 0, GETPOST('date_start'.$predef.'month'), GETPOST('date_start'.$predef.'day'), GETPOST('date_start'.$predef.'year'));
		$date_end=dol_mktime(0, 0, 0, GETPOST('date_end'.$predef.'month'), GETPOST('date_end'.$predef.'day'), GETPOST('date_end'.$predef.'year'));
		$price_base_type = (GETPOST('price_base_type', 'alpha')?GETPOST('price_base_type', 'alpha'):'HT');

		// Ecrase $pu par celui du produit
        // Ecrase $desc par celui du produit
        // Ecrase $txtva par celui du produit
        // Ecrase $base_price_type par celui du produit
        if ($_POST['idprod'])
 		{
			$prod = new Product($db);
			$prod->fetch($idprod);

			$label = ((GETPOST('product_label') && GETPOST('product_label')!=$prod->label)?GETPOST('product_label'):'');

			// Update if prices fields are defined
			if (GETPOST('usenewaddlineform'))
			{
				$pu_ht=price2num($price_ht, 'MU');
				$pu_ttc=price2num(GETPOST('price_ttc'), 'MU');
				$tva_npr=(preg_match('/\*/', $tva_tx)?1:0);
				$tva_tx=str_replace('*','', $tva_tx);
				$desc = $product_desc;
			}
            else
            {
				$tva_tx = get_default_tva($mysoc,$object->client,$prod->id);
				$tva_npr = get_default_npr($mysoc,$object->client,$prod->id);

				// multiprix
				if (! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($object->client->price_level))
				{
					$pu_ht = $prod->multiprices[$object->client->price_level];
					$pu_ttc = $prod->multiprices_ttc[$object->client->price_level];
					$price_min = $prod->multiprices_min[$object->client->price_level];
					$price_base_type = $prod->multiprices_base_type[$object->client->price_level];
				}
				else
				{
					$pu_ht = $prod->price;
					$pu_ttc = $prod->price_ttc;
					$price_min = $prod->price_min;
					$price_base_type = $prod->price_base_type;
				}

				// On reevalue prix selon taux tva car taux tva transaction peut etre different
				// de ceux du produit par defaut (par exemple si pays different entre vendeur et acheteur).
				if ($tva_tx != $prod->tva_tx)
				{
					if ($price_base_type != 'HT')
					{
						$pu_ht = price2num($pu_ttc / (1 + ($tva_tx/100)), 'MU');
					}
					else
					{
						$pu_ttc = price2num($pu_ht * (1 + ($tva_tx/100)), 'MU');
					}
				}

				$desc='';

				// Define output language
				if (! empty($conf->global->MAIN_MULTILANGS) && ! empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE))
				{
					$outputlangs = $langs;
					$newlang='';
					if (empty($newlang) && GETPOST('lang_id')) $newlang=GETPOST('lang_id');
					if (empty($newlang)) $newlang=$object->client->default_lang;
					if (! empty($newlang))
					{
						$outputlangs = new Translate("",$conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$desc = (! empty($prod->multilangs[$outputlangs->defaultlang]["description"])) ? $prod->multilangs[$outputlangs->defaultlang]["description"] : $prod->description;
				}
				else
				{
					$desc = $prod->description;
				}

            	$desc=dol_concatdesc($desc,$product_desc);

            	// Add custom code and origin country into description
            	if (empty($conf->global->MAIN_PRODUCT_DISABLE_CUSTOMCOUNTRYCODE) && (! empty($prod->customcode) || ! empty($prod->country_code)))
            	{
            		$tmptxt='(';
            		if (! empty($prod->customcode)) $tmptxt.=$langs->transnoentitiesnoconv("CustomCode").': '.$prod->customcode;
            		if (! empty($prod->customcode) && ! empty($prod->country_code)) $tmptxt.=' - ';
            		if (! empty($prod->country_code)) $tmptxt.=$langs->transnoentitiesnoconv("CountryOrigin").': '.getCountry($prod->country_code,0,$db,$langs,0);
            		$tmptxt.=')';
            		$desc= dol_concatdesc($desc, $tmptxt);
            	}
            }

			$type = $prod->type;
		}
		else
		{
			$pu_ht		= price2num($price_ht, 'MU');
			$pu_ttc		= price2num(GETPOST('price_ttc'), 'MU');
			$tva_npr	= (preg_match('/\*/', $tva_tx)?1:0);
			$tva_tx		= str_replace('*', '', $tva_tx);
			$label		= (GETPOST('product_label')?GETPOST('product_label'):'');
			$desc		= $product_desc;
			$type		= GETPOST('type');
		}

		// Margin
		$fournprice=(GETPOST('fournprice')?GETPOST('fournprice'):'');
		$buyingprice=(GETPOST('buying_price')?GETPOST('buying_price'):'');

		// Local Taxes
		$localtax1_tx= get_localtax($tva_tx, 1, $object->client);
		$localtax2_tx= get_localtax($tva_tx, 2, $object->client);

		$desc=dol_htmlcleanlastbr($desc);

		$info_bits=0;
		if ($tva_npr) $info_bits |= 0x01;

		if (! empty($price_min) && (price2num($pu_ht)*(1-price2num(GETPOST('remise_percent'))/100) < price2num($price_min)))
		{
			$mesg = $langs->trans("CantBeLessThanMinPrice",price2num($price_min,'MU').getCurrencySymbol($conf->currency));
			setEventMessage($mesg, 'errors');
		}
		else
		{
			// Insert line
			$result = $object->addline(
					$object->id,
					$desc,
					$pu_ht,
					GETPOST('qty'),
					$tva_tx,
					$localtax1_tx,
					$localtax2_tx,
					$idprod,
					GETPOST('remise_percent'),
					$info_bits,
					0,
					$price_base_type,
					$pu_ttc,
					$date_start,
					$date_end,
					$type,
					-1,
					0,
					GETPOST('fk_parent_line'),
					$fournprice,
					$buyingprice,
					$label
			);

            if ($result > 0)
    	    {
				$ret=$object->fetch($object->id);    // Reload to get new records

				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				{
					// Define output language
					$outputlangs = $langs;
					$newlang=GETPOST('lang_id','alpha');
					if (! empty($conf->global->MAIN_MULTILANGS) && empty($newlang)) $newlang=$object->client->default_lang;
					if (! empty($newlang))
					{
						$outputlangs = new Translate("",$conf);
						$outputlangs->setDefaultLang($newlang);
					}

                    $ret=$object->fetch($id);    // Reload to get new records
                    repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
				}

				unset($_POST['qty']);
				unset($_POST['type']);
				unset($_POST['idprod']);
				unset($_POST['remise_percent']);
				unset($_POST['price_ht']);
				unset($_POST['price_ttc']);
				unset($_POST['tva_tx']);
				unset($_POST['product_ref']);
				unset($_POST['product_label']);
				unset($_POST['product_desc']);
				unset($_POST['fournprice']);
				unset($_POST['buying_price']);

				// old method
				unset($_POST['np_desc']);
				unset($_POST['dp_desc']);
       		}
			else
			{
				setEventMessage($object->error, 'errors');
			}
		}
	}
}

/*
 *  Mise a jour d'une ligne dans la reparation
 */
else if ($action == 'updateligne' && (($user->rights->repair->CreateEstimate) || ($user->rights->repair->MakeRepair)) && $_POST['save'] == $langs->trans('Save'))
{
    if (! $object->fetch($id) > 0) dol_print_error($db);
    $object->fetch_thirdparty();

    // Clean parameters
    $date_start='';
    $date_end='';
    $date_start=dol_mktime(0, 0, 0, $_POST['date_start'.$suffixe.'month'], $_POST['date_start'.$suffixe.'day'], $_POST['date_start'.$suffixe.'year']);
    $date_end=dol_mktime(0, 0, 0, $_POST['date_end'.$suffixe.'month'], $_POST['date_end'.$suffixe.'day'], $_POST['date_end'.$suffixe.'year']);
    $description=dol_htmlcleanlastbr($_POST['desc']);
    $up_ht=GETPOST('pu')?GETPOST('pu'):GETPOST('subprice');

    // Define info_bits
    $info_bits=0;
    if (preg_match('/\*/',$_POST['tva_tx'])) $info_bits |= 0x01;

    // Define vat_rate
    $vat_rate=$_POST['tva_tx'];
    $vat_rate=str_replace('*','',$vat_rate);
    $localtax1_rate=get_localtax($vat_rate,1,$object->client);
    $localtax2_rate=get_localtax($vat_rate,2,$object->client);

    // Check parameters
    if (empty($_POST['productid']) && $_POST["type"] < 0)
    {
        $mesg = '<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Type")).'</div>';
        $result = -1 ;
    }

    // Define special_code for special lines
    $special_code=0;
    if (empty($_POST['qty'])) $special_code=3;

    // Check minimum price
    if(! empty($_POST['productid']))
    {
        $productid = $_POST['productid'];
        $product = new Product($db);
        $product->fetch($productid);
        $type=$product->type;
        $price_min = $product->price_min;
        if ($conf->global->PRODUIT_MULTIPRICES && $object->client->price_level)	$price_min = $product->multiprices_min[$object->client->price_level];
    }
    if ($price_min && GETPOST('productid') && (price2num($up_ht)*(1-price2num($_POST['remise_percent'])/100) < price2num($price_min)))
    {
        $mesg = '<div class="error">'.$langs->trans("CantBeLessThanMinPrice",price2num($price_min,'MU').' '.$langs->trans("Currency".$conf->currency)).'</div>' ;
        $result=-1;
    }

    // Define params
    if (! empty($_POST['productid']))
    {
        $type=$product->type;
    }
    else
    {
        $type=$_POST["type"];
    }

    if ($result >= 0)
    {
        $result = $object->updateline(
            $_POST['lineid'],
            $description,
            $up_ht,
            $_POST['qty'],
            $_POST['remise_percent'],
            $vat_rate,
            $localtax1_rate,
            $localtax2_rate,
            'HT',
            $info_bits,
            $date_start,
            $date_end,
            $type,
            $_POST['fk_parent_line']
        );

        if ($result >= 0)
        {
            // Define output language
            $outputlangs = $langs;
            $newlang='';
            if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
            if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
            if (! empty($newlang))
            {
                $outputlangs = new Translate("",$conf);
                $outputlangs->setDefaultLang($newlang);
            }
            if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
            {
                $ret=$object->fetch($id);    // Reload to get new records
                repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
            }
        }
        else
        {
            dol_print_error($db,$object->error);
            exit;
        }
    }
}

else if ($action == 'updateligne' && (($user->rights->repair->CreateEstimate) || ($user->rights->repair->MakeRepair) ) && $_POST['cancel'] == $langs->trans('Cancel'))
{
    Header('Location: fiche.php?id='.$id);   // Pour reaffichage de la fiche en cours d'edition
    exit;
}

else if ($action == 'createestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->createEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
/*    if ($result	>= 0)
    {
        // Define output language
        $outputlangs = $langs;
        $newlang='';
        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
        if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
        if (! empty($newlang))
        {
            $outputlangs = new Translate("",$conf);
            $outputlangs->setDefaultLang($newlang);
        }
        if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
    }
*/
}

else if ($action == 'finishestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->finishEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'cancelestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->cancelEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'validateestimate' && $user->rights->repair->ValidateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->validateEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'modifyestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->modifyEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'acceptedestimate' && $user->rights->repair->ValidateReplies)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->acceptedEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'refusedestimate' && $user->rights->repair->ValidateReplies)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->refusedEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'unvalidestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->unvalidEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'makerepair' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->makeRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'unaccepteestimate' && $user->rights->repair->CreateEstimate)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->unaccepteEstimate($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'unvalidrepair' && $user->rights->repair->MakeRepair)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->unvalidRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'finishrepair' && $user->rights->repair->MakeRepair)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->finishRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'cancelrepair' && $user->rights->repair->MakeRepair)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->cancelRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'validaterepair' && $user->rights->repair->ValidateRepair)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->validateRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'modifyrepair' && $user->rights->repair->MakeRepair)
{

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    $result=$object->modifyRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'confirm_close' && $confirm == 'yes' && $user->rights->repair->cloturer)
{
    $object->fetch($id);		// Load order and lines

    $result = $object->cloture($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'UnvalideRepair' && $user->rights->repair->ValidateRepair)
{
    $object->fetch($id);		// Load order and lines

    $result = $object->unvalideRepair($user);
    if ($result < 0) $mesgs=$object->errors;
}

else if ($action == 'classifybilled')
{
    $object->fetch($id);
    $object->classer_facturee();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
else if ($action == 'confirm_validate' && $confirm == 'yes' && $user->rights->repair->valider)
{
    $idwarehouse=GETPOST('idwarehouse');

    $object->fetch($id);	// Load order and lines
    $object->fetch_thirdparty();

    // Check parameters
    if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
    {
        if (! $idwarehouse || $idwarehouse == -1)
        {
            $error++;
            $errors[]=$langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse"));
            $action='';
        }
    }

    if (! $error)
    {
        $result=$object->valid($user,$idwarehouse);
        if ($result	>= 0)
        {
            // Define output language
            $outputlangs = $langs;
            $newlang='';
            if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
            if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
            if (! empty($newlang))
            {
                $outputlangs = new Translate("",$conf);
                $outputlangs->setDefaultLang($newlang);
            }
            if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
        }
    }
}

// Go back to draft status
else if ($action == 'confirm_modif' && $user->rights->repair->creer)
{
    $idwarehouse=GETPOST('idwarehouse');

    $object->fetch($id);		// Load order and lines
    $object->fetch_thirdparty();

    // Check parameters
    if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
    {
        if (! $idwarehouse || $idwarehouse == -1)
        {
            $error++;
            $errors[]=$langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse"));
            $action='';
        }
    }

	if (! $error)
	{
	    $result = $object->set_draft($user,$idwarehouse);
	    if ($result	>= 0)
	    {
	        // Define output language
	        $outputlangs = $langs;
	        $newlang='';
	        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
	        if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
	        if (! empty($newlang))
	        {
	            $outputlangs = new Translate("",$conf);
	            $outputlangs->setDefaultLang($newlang);
	        }
	        if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
	        {
                $ret=$object->fetch($id);    // Reload to get new records
	            repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
	        }
	    }
	}
}

else if ($action == 'confirm_cancel' && $confirm == 'yes' && $user->rights->repair->annuler)
{
    $idwarehouse=GETPOST('idwarehouse');

    $object->fetch($id);		// Load order and lines
    $object->fetch_thirdparty();

    // Check parameters
    if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
    {
        if (! $idwarehouse || $idwarehouse == -1)
        {
            $error++;
            $errors[]=$langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse"));
            $action='';
        }
    }

	if (! $error)
	{
	    $result = $object->cancel($user,$idwarehouse);
	}
}


/*
 * Ordonnancement des lignes
 */

else if ($action == 'up' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->fetch_thirdparty();
    $object->line_up($_GET['rowid']);

    // Define output language
    $outputlangs = $langs;
    $newlang='';
    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
    if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
    if (! empty($newlang))
    {
        $outputlangs = new Translate("",$conf);
        $outputlangs->setDefaultLang($newlang);
    }

    if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);

    Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id.'#'.$_GET['rowid']);
    exit;
}

else if ($action == 'down' && $user->rights->repair->creer)
{
    $object->fetch($id);
    $object->fetch_thirdparty();
    $object->line_down($_GET['rowid']);

    // Define output language
    $outputlangs = $langs;
    $newlang='';
    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
    if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
    if (! empty($newlang))
    {
        $outputlangs = new Translate("",$conf);
        $outputlangs->setDefaultLang($newlang);
    }
    if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);

    Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id.'#'.$_GET['rowid']);
    exit;
}

else if ($action == 'builddoc')	// In get or post
{
    /*
     * Generate order document
     * define into /repair/core/modules/repair/modules_repair.php
     */

    // Sauvegarde le dernier modele choisi pour generer un document
    $result=$object->fetch($id);
    $object->fetch_thirdparty();

    if ($_REQUEST['model'])
    {
        $object->setDocModel($user, $_REQUEST['model']);
    }

    // Define output language
    $outputlangs = $langs;
    $newlang='';
    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
    if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
    if (! empty($newlang))
    {
        $outputlangs = new Translate("",$conf);
        $outputlangs->setDefaultLang($newlang);
    }
    $result=repair_pdf_create($db, $object, $object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
    if ($result <= 0)
    {
        dol_print_error($db,$result);
        exit;
    }
    else
    {
        Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.(empty($conf->global->MAIN_JUMP_TAG)?'':'#builddoc'));
        exit;
    }
}

// Remove file in doc form
else if ($action == 'remove_file')
{
    if ($object->fetch($id))
    {
        require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

        $object->fetch_thirdparty();

        $langs->load("other");
        $upload_dir = $conf->repair->dir_output;
        $file = $upload_dir . '/' . GETPOST('file');
        dol_delete_file($file,0,0,0,$object);
        $mesg = '<div class="ok">'.$langs->trans("FileWasRemoved",GETPOST('file')).'</div>';
    }
}

/*
 * Add file in email form
 */
if ($_POST['addfile'])
{
    require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

    // Set tmp user directory TODO Use a dedicated directory for temp mails files
    $vardir=$conf->user->dir_output."/".$user->id;
    $upload_dir_tmp = $vardir.'/temp';

    $mesg=dol_add_file_process($upload_dir_tmp,0,0);

    $action ='presend';
}

/*
 * Remove file in email form
 */
if (! empty($_POST['removedfile']))
{
    require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

    // Set tmp user directory
    $vardir=$conf->user->dir_output."/".$user->id;
    $upload_dir_tmp = $vardir.'/temp';

	// TODO Delete only files that was uploaded from email form
    $mesg=dol_remove_file_process($_POST['removedfile'],0);

    $action ='presend';
}

/*
 * Send mail
 */
if ($action == 'send' && ! $_POST['addfile'] && ! $_POST['removedfile'] && ! $_POST['cancel'])
{
    $langs->load('mails');

    $result=$object->fetch($id);
    $result=$object->fetch_thirdparty();

    if ($result > 0)
    {
//        $ref = dol_sanitizeFileName($object->ref);
//        $file = $conf->repair->dir_output . '/' . $ref . '/' . $ref . '.pdf';

//        if (is_readable($file))
//        {
            if ($_POST['sendto'])
            {
                // Le destinataire a ete fourni via le champ libre
                $sendto = $_POST['sendto'];
                $sendtoid = 0;
            }
            elseif ($_POST['receiver'] != '-1')
            {
                // Recipient was provided from combo list
                if ($_POST['receiver'] == 'thirdparty') // Id of third party
                {
                    $sendto = $object->client->email;
                    $sendtoid = 0;
                }
                else	// Id du contact
                {
                    $sendto = $object->client->contact_get_property($_POST['receiver'],'email');
                    $sendtoid = $_POST['receiver'];
                }
            }

            if (dol_strlen($sendto))
            {
                $langs->load("commercial");

                $from = $_POST['fromname'] . ' <' . $_POST['frommail'] .'>';
                $replyto = $_POST['replytoname']. ' <' . $_POST['replytomail'].'>';
                $message = $_POST['message'];
                $sendtocc = $_POST['sendtocc'];
                $deliveryreceipt = $_POST['deliveryreceipt'];

                if ($_POST['action'] == 'send')
                {
                    if (dol_strlen($_POST['subject'])) $subject=$_POST['subject'];
                    else $subject = $langs->transnoentities('Order').' '.$object->ref;
                    $actiontypecode='AC_COM';
                    $actionmsg = $langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto.".\n";
                    if ($message)
                    {
                        $actionmsg.=$langs->transnoentities('MailTopic').": ".$subject."\n";
                        $actionmsg.=$langs->transnoentities('TextUsedInTheMessageBody').":\n";
                        $actionmsg.=$message;
                    }
                    $actionmsg2=$langs->transnoentities('Action'.$actiontypecode);
                }

                // Create form object
                include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php');
                $formmail = new FormMail($db);

                $attachedfiles=$formmail->get_attached_files();
                $filepath = $attachedfiles['paths'];
                $filename = $attachedfiles['names'];
                $mimetype = $attachedfiles['mimes'];

                // Send mail
                require_once(DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php');
                $mailfile = new CMailFile($subject,$sendto,$from,$message,$filepath,$mimetype,$filename,$sendtocc,'',$deliveryreceipt);
                if ($mailfile->error)
                {
                    $mesg='<div class="error">'.$mailfile->error.'</div>';
                }
                else
                {
                    $result=$mailfile->sendfile();
                    if ($result)
                    {
                        $mesg=$langs->trans('MailSuccessfulySent',$mailfile->getValidAddress($from,2),$mailfile->getValidAddress($sendto,2));	// Must not contains "

                        $error=0;

                        // Initialisation donnees
                        $object->sendtoid		= $sendtoid;
                        $object->actiontypecode	= $actiontypecode;
                        $object->actionmsg		= $actionmsg;
                        $object->actionmsg2		= $actionmsg2;
                        $object->fk_element		= $object->id;
                        $object->elementtype	= $object->element;

                        // Appel des triggers
                        include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                        $interface=new Interfaces($db);
                        $result=$interface->run_triggers('ORDER_SENTBYMAIL',$object,$user,$langs,$conf);
                        if ($result < 0) { $error++; $this->errors=$interface->errors; }
                        // Fin appel triggers

                        if ($error)
                        {
                            dol_print_error($db);
                        }
                        else
                        {
                            // Redirect here
                            // This avoid sending mail twice if going out and then back to page
                            Header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.'&mesg='.urlencode($mesg));
                            exit;
                        }
                    }
                    else
                    {
                        $langs->load("other");
                        $mesg='<div class="error">';
                        if ($mailfile->error)
                        {
                            $mesg.=$langs->trans('ErrorFailedToSendMail',$from,$sendto);
                            $mesg.='<br>'.$mailfile->error;
                        }
                        else
                        {
                            $mesg.='No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
                        }
                        $mesg.='</div>';
                    }
                }
/*            }
            else
            {
                $langs->load("other");
                $mesg='<div class="error">'.$langs->trans('ErrorMailRecipientIsEmpty').' !</div>';
                $action='presend';
                dol_syslog('Recipient email is empty');
            }*/
        }
        else
        {
            $langs->load("errors");
            $mesg='<div class="error">'.$langs->trans('ErrorCantReadFile',$file).'</div>';
            dol_syslog('Failed to read file: '.$file);
        }
    }
    else
    {
        $langs->load("other");
        $mesg='<div class="error">'.$langs->trans('ErrorFailedToReadEntity',$langs->trans("Repair")).'</div>';
        dol_syslog($langs->trans('ErrorFailedToReadEntity', $langs->trans("Repair")));
    }
}

if (! empty($conf->global->MAIN_DISABLE_CONTACTS_TAB))
{
	if ($action == 'addcontact' && $user->rights->repair->creer)
	{
		$result = $object->fetch($id);

		if ($result > 0 && $id > 0)
		{
			$contactid = (GETPOST('userid') ? GETPOST('userid') : GETPOST('contactid'));
			$result = $result = $object->add_contact($contactid, $_POST["type"], $_POST["source"]);
		}

		if ($result >= 0)
		{
			Header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
			exit;
		}
		else
		{
			if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
			{
				$langs->load("errors");
				$mesg = '<div class="error">'.$langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType").'</div>';
			}
			else
			{
				$mesg = '<div class="error">'.$object->error.'</div>';
			}
		}
	}

	// bascule du statut d'un contact
	else if ($action == 'swapstatut' && $user->rights->repair->creer)
	{
		if ($object->fetch($id))
		{
			$result=$object->swapContactStatus(GETPOST('ligne'));
		}
		else
		{
			dol_print_error($db);
		}
	}

	// Efface un contact
	else if ($action == 'deletecontact' && $user->rights->repair->creer)
	{
		$object->fetch($id);
		$result = $object->delete_contact($lineid);

		if ($result >= 0)
		{
			Header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
			exit;
		}
		else {
			dol_print_error($db);
		}
	}
}


/*
 *	View
 */

llxHeader('',$langs->trans('Repair'));
$form = new Form($db);
$formfile = new FormFile($db);
$formorder = new FormOrder($db);
$repair = new Repair($db);


/*********************************************************************
 *
 * Mode creation
 *
 *********************************************************************/
if ($action == 'create' && $user->rights->repair->creer)
{
	//WYSIWYG Editor
	require_once(DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php");

    print_fiche_titre($langs->trans('CreateRepair'));

    dol_htmloutput_mesg($mesg,$mesgs,'error');

    $soc = new Societe($db);
    if ($socid) $res=$soc->fetch($socid);

    if (GETPOST('origin') && GETPOST('originid'))
    {
        // Parse element/subelement (ex: project_task)
        $element = $subelement = GETPOST('origin');
        if (preg_match('/^([^_]+)_([^_]+)/i',GETPOST('origin'),$regs))
        {
            $element = $regs[1];
            $subelement = $regs[2];
        }

        if ($element == 'project')
        {
            $projectid=GETPOST('originid');
        }
        else
        {
            // For compatibility
//            if ($element == 'repair')   { $element = $subelement = 'repair'; }
            if ($element == 'propal')   { $element = 'comm/propal'; $subelement = 'propal'; }
            if ($element == 'contract') { $element = $subelement = 'contrat'; }

            dol_include_once('/'.$element.'/class/'.$subelement.'.class.php');

            $classname = ucfirst($subelement);
            $objectsrc = new $classname($db);
            $objectsrc->fetch(GETPOST('originid'));
            if (empty($objectsrc->lines) && method_exists($objectsrc,'fetch_lines'))  $objectsrc->fetch_lines();
            $objectsrc->fetch_thirdparty();

            $projectid          = (!empty($objectsrc->fk_project)?$object->fk_project:'');
            $ref_client         = (!empty($objectsrc->ref_client)?$object->ref_client:'');

            $soc = $objectsrc->client;
            $cond_reglement_id	= (!empty($objectsrc->cond_reglement_id)?$objectsrc->cond_reglement_id:(!empty($soc->cond_reglement_id)?$soc->cond_reglement_id:1));
            $mode_reglement_id	= (!empty($objectsrc->mode_reglement_id)?$objectsrc->mode_reglement_id:(!empty($soc->mode_reglement_id)?$soc->mode_reglement_id:0));
            $availability_id	= (!empty($objectsrc->availability_id)?$objectsrc->availability_id:(!empty($soc->availability_id)?$soc->availability_id:0));
            $demand_reason_id	= (!empty($objectsrc->demand_reason_id)?$objectsrc->demand_reason_id:(!empty($soc->demand_reason_id)?$soc->demand_reason_id:0));
            $remise_percent		= (!empty($objectsrc->remise_percent)?$objectsrc->remise_percent:(!empty($soc->remise_percent)?$soc->remise_percent:0));
            $remise_absolue		= (!empty($objectsrc->remise_absolue)?$objectsrc->remise_absolue:(!empty($soc->remise_absolue)?$soc->remise_absolue:0));
            $dateinvoice		= empty($conf->global->MAIN_AUTOFILL_DATE)?-1:0;

            $note_private		= (! empty($objectsrc->note) ? $objectsrc->note : (! empty($objectsrc->note_private) ? $objectsrc->note_private : ''));
            $note_public		= (! empty($objectsrc->note_public) ? $objectsrc->note_public : '');

            // Object source contacts list
            $srccontactslist = $objectsrc->liste_contact(-1,'external',1);
        }
    }
    else
    {
        $cond_reglement_id  = $soc->cond_reglement_id;
        $mode_reglement_id  = $soc->mode_reglement_id;
        $availability_id    = $soc->availability_id;
        $demand_reason_id   = $soc->demand_reason_id;
        $remise_percent     = $soc->remise_percent;
        $remise_absolue     = 0;
        $dateinvoice        = empty($conf->global->MAIN_AUTOFILL_DATE)?-1:0;
    }
    $absolute_discount=$soc->getAvailableDiscounts();



    $nbrow=10;

    print '<form name="crea_repair" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="add">';
    print '<input type="hidden" name="socid" value="'.$soc->id.'">' ."\n";
    print '<input type="hidden" name="remise_percent" value="'.$soc->remise_client.'">';
    print '<input name="facnumber" type="hidden" value="provisoire">';
    print '<input type="hidden" name="origin" value="'.GETPOST('origin').'">';
    print '<input type="hidden" name="originid" value="'.GETPOST('originid').'">';

    print '<table class="border" width="100%">';

    // Reference
//    print '<tr><td class="fieldrequired">'.$langs->trans('Ref').'</td><td colspan="2">'.$repair->getNextNumRef($soc).'</td></tr>';

    // Reference client
    print '<tr><td>'.$langs->trans('RefCustomer').'</td><td colspan="2">';
    print '<input type="text" name="ref_client" value=""></td>';
    print '</tr>';


// If javascript on, we show option individual
	if ($conf->use_javascript_ajax)
	{
		$formrepair = new FormRepair($db);
		print '<tr class="individualline"><td>'.$langs->trans("RepairSupport").'</td><td>';
		print $formrepair->select_repair_support($object->support_id,"support_id").'</td>';
	}


    // Client
    print '<tr><td class="fieldrequired">'.$langs->trans('Customer').'</td><td colspan="2">'.$soc->getNomUrl(1).'</td></tr>';

    /*
     * Contact de la reparation
     */
    print "<tr><td>".$langs->trans("DefaultContact").'</td><td colspan="2">';
    $form->select_contacts($soc->id,$setcontact,'contactidp',1,$srccontactslist);
    print '</td></tr>';
//<Tathar>
	// Marque
    print '<tr><td class="fieldrequired">'.$langs->trans('MachineTrademark').'</td><td colspan="2">';
    print '<input type="text" name="trademark" value=""></td>';
    print '</tr>';
	// Numero de Model
    print '<tr><td>'.$langs->trans('MachineNModel').'</td><td colspan="2">';
    print '<input type="text" name="n_model" value=""></td>';
    print '</tr>';
	// Modele
    print '<tr><td class="fieldrequired">'.$langs->trans('MachineModel').'</td><td colspan="2">';
    print '<input type="text" name="model" value=""></td>';
    print '</tr>';
	// Numero de Serie
    print '<tr><td class="fieldrequired">'.$langs->trans('MachineNSerie').'</td><td colspan="2">';
    print '<input type="text" name="serial_num" value=""></td>';
    print '</tr>';
	// Panne
    print '<tr>';
    print '<td class="fieldrequired" valign="top">'.$langs->trans('RepairBreakdown').'</td>';
    print '<td valign="top" colspan="2">';

    $doleditor = new DolEditor('breakdown', $breakdown, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
    print $doleditor->Create(1);
    print '</td></tr>';
	// Accessory
    print '<tr>';
    print '<td class="fieldrequired" valign="top">'.$langs->trans('RepairAccessory').'</td>';
    print '<td valign="top" colspan="2">';

    $doleditor = new DolEditor('accessory', $accessory, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
    print $doleditor->Create(1);
    print '</td></tr>';

//</Tathar>
    // Ligne info remises tiers
    print '<tr><td>'.$langs->trans('Discounts').'</td><td colspan="2">';
    if ($soc->remise_client) print $langs->trans("CompanyHasRelativeDiscount",$soc->remise_client);
    else print $langs->trans("CompanyHasNoRelativeDiscount");
    print '. ';
    $absolute_discount=$soc->getAvailableDiscounts();
    if ($absolute_discount) print $langs->trans("CompanyHasAbsoluteDiscount",price($absolute_discount),$langs->trans("Currency".$conf->currency));
    else print $langs->trans("CompanyHasNoAbsoluteDiscount");
    print '.';
    print '</td></tr>';

    // Date
    print '<tr><td class="fieldrequired">'.$langs->trans('Date').'</td><td colspan="2">';
    $form->select_date('','re','','','',"crea_repair",1,1);
    print '</td></tr>';

    // Date de livraison
    print "<tr><td>".$langs->trans("DeliveryDate").'</td><td colspan="2">';
    if ($conf->global->DATE_LIVRAISON_WEEK_DELAY)
    {
        $datedelivery = time() + ((7*$conf->global->DATE_LIVRAISON_WEEK_DELAY) * 24 * 60 * 60);
    }
    else
    {
        $datedelivery=empty($conf->global->MAIN_AUTOFILL_DATE)?-1:0;
    }
    $form->select_date($datedelivery,'liv_','','','',"crea_repair",1,1);
    print "</td></tr>";

    // Conditions de reglement
    print '<tr><td nowrap="nowrap">'.$langs->trans('PaymentConditionsShort').'</td><td colspan="2">';
    $form->select_conditions_paiements($soc->cond_reglement,'cond_reglement_id',-1,1);
    print '</td></tr>';

    // Mode de reglement
    print '<tr><td>'.$langs->trans('PaymentMode').'</td><td colspan="2">';
    $form->select_types_paiements($soc->mode_reglement,'mode_reglement_id');
    print '</td></tr>';

    // Delivery delay
    print '<tr><td>'.$langs->trans('AvailabilityPeriod').'</td><td colspan="2">';
    $form->select_availability($propal->availability,'availability_id','',1);
    print '</td></tr>';

    // What trigger creation
    print '<tr><td>'.$langs->trans('Source').'</td><td colspan="2">';
    $form->select_demand_reason((GETPOST("origin")=='propal'?'SRC_COMM':''),'demand_reason_id','',1);
    print '</td></tr>';

    // Project
    if ($conf->projet->enabled)
    {
        $projectid = 0;
        if (isset($_GET["origin"]) && $_GET["origin"] == 'project') $projectid = ($_GET["originid"]?$_GET["originid"]:0);

        print '<tr><td>'.$langs->trans('Project').'</td><td colspan="2">';
        $numprojet=select_projects($soc->id,$projectid);
        if ($numprojet==0)
        {
            print ' &nbsp; <a href="'.DOL_URL_ROOT.'/projet/fiche.php?socid='.$soc->id.'&action=create">'.$langs->trans("AddProject").'</a>';
        }
        print '</td></tr>';
    }

    // Other attributes
    $parameters=array('objectsrc' => $objectsrc, 'colspan' => ' colspan="3"');
    $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
    if (empty($reshook) && ! empty($extrafields->attribute_label))
    {
        foreach($extrafields->attribute_label as $key=>$label)
        {
            $value=(isset($_POST["options_".$key])?$_POST["options_".$key]:$object->array_options["options_".$key]);
            print "<tr><td>".$label.'</td><td colspan="3">';
            print $extrafields->showInputField($key,$value);
            print '</td></tr>'."\n";
        }
    }

    // Template to use by default
    print '<tr><td>'.$langs->trans('Model').'</td>';
    print '<td colspan="2">';
    include_once(DOL_DOCUMENT_ROOT.'/repair/core/modules/repair/modules_repair.php');
    $liste=ModeleRepair::liste_modeles($db);
    print $form->selectarray('model_pdf',$liste,$conf->global->REPAIR_ADDON_PDF);
    print "</td></tr>";

    // Note publique
    print '<tr>';
    print '<td class="border" valign="top">'.$langs->trans('NotePublic').'</td>';
    print '<td valign="top" colspan="2">';

    $doleditor = new DolEditor('note_public', $note_public, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
    print $doleditor->Create(1);
    //print '<textarea name="note_public" wrap="soft" cols="70" rows="'.ROWS_3.'">'.$note_public.'</textarea>';
    print '</td></tr>';

    // Note privee
    if (! $user->societe_id)
    {
        print '<tr>';
        print '<td class="border" valign="top">'.$langs->trans('NotePrivate').'</td>';
        print '<td valign="top" colspan="2">';

        $doleditor = new DolEditor('note', $note_private, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
        print $doleditor->Create(1);
        //print '<textarea name="note" wrap="soft" cols="70" rows="'.ROWS_3.'">'.$note_private.'</textarea>';
        print '</td></tr>';
    }

    if (is_object($objectsrc))
    {
        // TODO for compatibility
        if ($_GET['origin'] == 'contrat')
        {
            // Calcul contrat->price (HT), contrat->total (TTC), contrat->tva
            $objectsrc->remise_absolue=$remise_absolue;
            $objectsrc->remise_percent=$remise_percent;
            $objectsrc->update_price(1);
        }

        print "\n<!-- ".$classname." info -->";
        print "\n";
        print '<input type="hidden" name="amount"         value="'.$objectsrc->total_ht.'">'."\n";
        print '<input type="hidden" name="total"          value="'.$objectsrc->total_ttc.'">'."\n";
        print '<input type="hidden" name="tva"            value="'.$objectsrc->total_tva.'">'."\n";
        print '<input type="hidden" name="origin"         value="'.$objectsrc->element.'">';
        print '<input type="hidden" name="originid"       value="'.$objectsrc->id.'">';

        $newclassname=$classname;
        if ($newclassname=='Propal') $newclassname='CommercialProposal';
        print '<tr><td>'.$langs->trans($newclassname).'</td><td colspan="2">'.$objectsrc->getNomUrl(1).'</td></tr>';
        print '<tr><td>'.$langs->trans('TotalHT').'</td><td colspan="2">'.price($objectsrc->total_ht).'</td></tr>';
        print '<tr><td>'.$langs->trans('TotalVAT').'</td><td colspan="2">'.price($objectsrc->total_tva)."</td></tr>";
        if ($mysoc->country_code=='ES')
        {
            if ($mysoc->localtax1_assuj=="1") //Localtax1 RE
            {
                print '<tr><td>'.$langs->transcountry("AmountLT1",$mysoc->country_code).'</td><td colspan="2">'.price($objectsrc->total_localtax1)."</td></tr>";
            }

            if ($mysoc->localtax2_assuj=="1") //Localtax2 IRPF
            {
                print '<tr><td>'.$langs->transcountry("AmountLT2",$mysoc->country_code).'</td><td colspan="2">'.price($objectsrc->total_localtax2)."</td></tr>";
            }
        }
        print '<tr><td>'.$langs->trans('TotalTTC').'</td><td colspan="2">'.price($objectsrc->total_ttc)."</td></tr>";
    }
    else
    {
        if ($conf->global->PRODUCT_SHOW_WHEN_CREATE)
        {
            /*
             * Services/produits predefinis
             */
            $NBLINES=8;

            print '<tr><td colspan="3">';

            print '<table class="noborder">';
            print '<tr><td>'.$langs->trans('ProductsAndServices').'</td>';
            print '<td>'.$langs->trans('Qty').'</td>';
            print '<td>'.$langs->trans('ReductionShort').'</td>';
            print '</tr>';
            for ($i = 1 ; $i <= $NBLINES ; $i++)
            {
                print '<tr><td>';
                // multiprix
                if($conf->global->PRODUIT_MULTIPRICES)
                print $form->select_produits('','idprod'.$i,'',$conf->product->limit_size,$soc->price_level);
                else
                print $form->select_produits('','idprod'.$i,'',$conf->product->limit_size);
                print '</td>';
                print '<td><input type="text" size="3" name="qty'.$i.'" value="1"></td>';
                print '<td><input type="text" size="3" name="remise_percent'.$i.'" value="'.$soc->remise_client.'">%</td></tr>';
            }

            print '</table>';
            print '</td></tr>';
        }
    }

    print '</table>';
    // Button "Create Draft"
    print '<br><center><input type="submit" class="button" name="bouton" value="'.$langs->trans('CreateDraft').'"></center>';

    print '</form>';


    // Show origin lines
    if (is_object($objectsrc))
    {
        $title=$langs->trans('ProductsAndServices');
        print_titre($title);

        print '<table class="noborder" width="100%">';

        $objectsrc->printOriginLinesList($hookmanager);

        print '</table>';
    }

}
else
{
    /* *************************************************************************** */
    /*                                                                             */
    /* Mode vue et edition                                                         */
    /*                                                                             */
    /* *************************************************************************** */
    $now=dol_now();

    if ($id > 0 || ! empty($ref))
    {

        dol_htmloutput_mesg($mesg,$mesgs);
        dol_htmloutput_errors('',$errors);

        $product_static=new Product($db);

        $result=$object->fetch($id,$ref);
        if ($result > 0)
        {
            $soc = new Societe($db);
            $soc->fetch($object->socid);

            $author = new User($db);
            $author->fetch($object->user_author_id);

            $head = repair_prepare_head($object);
            dol_fiche_head($head, 'repair', $langs->trans("CustomerRepair"), 0, 'repair@repair');

            $formconfirm='';

            /*
             * Confirmation de la suppression de la réparation
             */
            if ($action == 'delete')
            {
                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteRepair'), $langs->trans('ConfirmDeleteRepair'), 'confirm_delete', '', 0, 1);
            }


            /*
             * Confirmation de la validation
             */
            if ($action == 'validate')
            {
                // on verifie si l'objet est en numerotation provisoire
                $ref = substr($object->ref, 1, 4);
                if ($ref == 'PROV')
                {
                    $numref = $object->getNextNumRef($soc);
                }
                else
                {
                    $numref = $object->ref;
                }

                $text=$langs->trans('ConfirmValidateRepair',$numref);
                if ($conf->notification->enabled)
                {
                    require_once(DOL_DOCUMENT_ROOT ."/core/class/notify.class.php");
                    $notify=new Notify($db);
                    $text.='<br>';
                    $text.=$notify->confirmMessage('NOTIFY_VAL_ORDER',$object->socid);
                }
                $formquestion=array();
                if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
                {
                    $langs->load("stocks");
                    require_once(DOL_DOCUMENT_ROOT."/product/class/html.formproduct.class.php");
                    $formproduct=new FormProduct($db);
                    $formquestion=array(
                    //'text' => $langs->trans("ConfirmClone"),
                    //array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
                    //array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
                    array('type' => 'other', 'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"),   'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'),'idwarehouse','',1)));
                }
                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateRepair'), $text, 'confirm_validate', $formquestion, 0, 1, 220);
            }

            // Confirm back to draft status
            if ($action == 'modif')
            {
                $text=$langs->trans('ConfirmUnvalidateRepair',$object->ref);
                $formquestion=array();
                if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
                {
                    $langs->load("stocks");
                    require_once(DOL_DOCUMENT_ROOT."/product/class/html.formproduct.class.php");
                    $formproduct=new FormProduct($db);
                    $formquestion=array(
                    //'text' => $langs->trans("ConfirmClone"),
                    //array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
                    //array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
                    array('type' => 'other', 'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockIncrease"),   'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'),'idwarehouse','',1)));
                }

                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('UnvalidateRepair'), $text, 'confirm_modif', $formquestion, "yes", 1, 220);
            }


            /*
             * Confirmation de la cloture
             */
            if ($action == 'close')
            {
                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('CloseRepair'), $langs->trans('ConfirmCloseRepair'), 'confirm_close', '', 0, 1);
            }

            /*
             * Confirmation de l'annulation
             */
            if ($action == 'cancel')
            {
                $text=$langs->trans('ConfirmCancelRepair',$object->ref);
                $formquestion=array();
                if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1))
                {
                    $langs->load("stocks");
                    require_once(DOL_DOCUMENT_ROOT."/product/class/html.formproduct.class.php");
                    $formproduct=new FormProduct($db);
                    $formquestion=array(
                    //'text' => $langs->trans("ConfirmClone"),
                    //array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
                    //array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
                    array('type' => 'other', 'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockIncrease"),   'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'),'idwarehouse','',1)));
                }

                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Cancel'), $text, 'confirm_cancel', $formquestion, 0, 1);
            }

            /*
             * Confirmation de la suppression d'une ligne produit
             */
            if ($action == 'ask_deleteline')
            {
                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
            }

            // Clone confirmation
            if ($action == 'clone')
            {
                // Create an array for form
                $formquestion=array(
                //'text' => $langs->trans("ConfirmClone"),
                //array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
                //array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
                array('type' => 'other', 'name' => 'socid',   'label' => $langs->trans("SelectThirdParty"),   'value' => $form->select_company(GETPOST('socid','int'),'socid','(s.client=1 OR s.client=3)'))
                );
                // Paiement incomplet. On demande si motif = escompte ou autre
                $formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id,$langs->trans('CloneRepair'),$langs->trans('ConfirmCloneRepair',$object->ref),'confirm_clone',$formquestion,'yes',1);
            }

            if (! $formconfirm)
            {
                $parameters=array('lineid'=>$lineid);
                $formconfirm=$hookmanager->executeHooks('formConfirm',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
            }

            // Print form confirm
            print $formconfirm;

            /*
             *   Repair
             */
            $nbrow=9;
            if ($conf->projet->enabled) $nbrow++;

            //Local taxes
            if ($mysoc->country_code=='ES')
            {
                if($mysoc->localtax1_assuj=="1") $nbrow++;
                if($mysoc->localtax2_assuj=="1") $nbrow++;
            }

            print '<table class="border" width="100%">';

            // Ref
            print '<tr><td width="18%">'.$langs->trans('Ref').'</td>';
            print '<td colspan="3">';
            print $form->showrefnav($object,'ref','',1,'ref','ref');
            print '</td>';
            print '</tr>';

            // Ref repair client
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('RefCustomer').'</td><td align="left">';
            print '</td>';
            if ($action != 'refcustomer' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=refcustomer&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'refcustomer')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_ref_client">';
                print '<input type="text" class="flat" size="20" name="ref_client" value="'.$object->ref_client.'">';
                print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->ref_client;
            }
            print '</td>';
            print '</tr>';

// If javascript on, Support
			if ($conf->use_javascript_ajax)
			{
	            print '<tr><td>';
	            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            	print $langs->trans('RepairSupport').'</td><td align="left">';
	            print '</td>';
            	if ($action != 'repairsupport' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=repairsupport&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            	print '</tr></table>';
            	print '</td><td colspan="3">';
            	if ($user->rights->repair->creer && $action == 'repairsupport')
            	{
					$formrepair = new FormRepair($db);
            	    print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
           	    	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                	print '<input type="hidden" name="action" value="set_support_id">';
					print $formrepair->select_repair_support($object->support_id,"support_id");
                	print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                	print '</form>';
            	}
            	else
            	{
            	    print $object->getSupportLabel();
            	}
            	print '</td>';
            	print '</tr>';

			}



            // Societe
            print '<tr><td>'.$langs->trans('Company').'</td>';
            print '<td colspan="3">'.$soc->getNomUrl(1).'</td>';
            print '</tr>';

			// Marque
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('MachineTrademark').'</td><td align="left">';
            print '</td>';
            if ($action != 'trademark' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=trademark&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'trademark')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_trademark">';
                print '<input type="text" class="flat" size="20" name="trademark" value="'.$object->trademark.'">';
                print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->trademark;
            }
            print '</td>';
            print '</tr>';

			// N° Modele
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('MachineNModel').'</td><td align="left">';
            print '</td>';
            if ($action != 'n_model' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=n_model&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'n_model')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_n_model">';
                print '<input type="text" class="flat" size="20" name="n_model" value="'.$object->n_model.'">';
                print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->n_model;
            }
            print '</td>';
            print '</tr>';

			// Modele
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('MachineModel').'</td><td align="left">';
            print '</td>';
            if ($action != 'model' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=model&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'model')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_model">';
                print '<input type="text" class="flat" size="20" name="model" value="'.$object->model.'">';
                print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->model;
            }
            print '</td>';
            print '</tr>';


			// If javascript on, Type
			if ($conf->use_javascript_ajax)
			{
				$formmachine = new FormMachine($db);

	            print '<tr><td>';
	            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            	print $langs->trans('MachineType').'</td><td align="left">';
	            print '</td>';
            	if ($action != 'type' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=type&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            	print '</tr></table>';
            	print '</td><td colspan="3">';
            	if ($user->rights->repair->creer && $action == 'type')
            	{
            	    print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
           	    	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                	print '<input type="hidden" name="action" value="set_type">';
					print $formmachine->select_machine_type($object->type_id,"type_id");
                	print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                	print '</form>';
            	}
            	else
            	{
            	    print $formmachine->get_machine_type($object->type_id);
            	}
            	print '</td>';
            	print '</tr>';

			}


			// N° Serie
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('MachineNSerie').'</td><td align="left">';
            print '</td>';
            if ($action != 'serial_num' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=serial_num&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'serial_num')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_serial_num">';
                print '<input type="text" class="flat" size="20" name="serial_num" value="'.$object->serial_num.'">';
                print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->serial_num;
            }
            print '</td>';
            print '</tr>';

			//WYSIWYG Editor
			require_once(DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php");
			// Breakdown
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('RepairBreakdown').'</td><td align="left">';
            print '</td>';
            if ($action != 'breakdown' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=breakdown&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'breakdown')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
				print '<table  width="100%" class="nobordernopadding"><tr><td nowrap="nowrap">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_breakdown">';
				$doleditor = new DolEditor('breakdown', $object->breakdown, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
    			print $doleditor->Create(1);
				print '</td><td align="LEFT" valign="middle" width="100%" >';
                print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</td></tr></table>';
				print '</form>';
            }
            else
            {
                print $object->breakdown;
            }
            print '</td>';
            print '</tr>';

			// Accessory
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
            print $langs->trans('RepairAccessory').'</td><td align="left">';
            print '</td>';
            if ($action != 'accessory' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=accessory&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($user->rights->repair->creer && $action == 'accessory')
            {
                print '<form action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
				print '<table  width="100%" class="nobordernopadding"><tr><td nowrap="nowrap">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="set_accessory">';
				$doleditor = new DolEditor('accessory', $object->accessory, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
    			print $doleditor->Create(1);
				print '</td><td align="LEFT" valign="middle" width="100%" >';
                print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</td></tr></table>';
				print '</form>';
            }
            else
            {
                print $object->accessory;
            }
            print '</td>';
            print '</tr>';


            // Ligne info remises tiers
            print '<tr><td>'.$langs->trans('Discounts').'</td><td colspan="3">';
            if ($soc->remise_client) print $langs->trans("CompanyHasRelativeDiscount",$soc->remise_client);
            else print $langs->trans("CompanyHasNoRelativeDiscount");
            print '. ';
            $absolute_discount=$soc->getAvailableDiscounts('','fk_facture_source IS NULL');
            $absolute_creditnote=$soc->getAvailableDiscounts('','fk_facture_source IS NOT NULL');
            $absolute_discount=price2num($absolute_discount,'MT');
            $absolute_creditnote=price2num($absolute_creditnote,'MT');
            if ($absolute_discount)
            {
                if ($object->statut > 0)
                {
                    print $langs->trans("CompanyHasAbsoluteDiscount",price($absolute_discount),$langs->transnoentities("Currency".$conf->currency));
                }
                else
                {
                    // Remise dispo de type non avoir
                    $filter='fk_facture_source IS NULL';
                    print '<br>';
                    $form->form_remise_dispo($_SERVER["PHP_SELF"].'?id='.$object->id,0,'remise_id',$soc->id,$absolute_discount,$filter);
                }
            }
            if ($absolute_creditnote)
            {
                print $langs->trans("CompanyHasCreditNote",price($absolute_creditnote),$langs->transnoentities("Currency".$conf->currency)).'. ';
            }
            if (! $absolute_discount && ! $absolute_creditnote) print $langs->trans("CompanyHasNoAbsoluteDiscount").'.';
            print '</td></tr>';

            // Date
            print '<tr><td>';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('Date');
            print '</td>';

            if ($action != 'editdate' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdate&amp;id='.$object->id.'">'.img_edit($langs->trans('SetDate'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="3">';
            if ($action == 'editdate')
            {
                print '<form name="setdate" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="setdate">';
                $form->select_date($object->date,'order_','','','',"setdate");
                print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->date ? dol_print_date($object->date,'daytext') : '&nbsp;';
            }
            print '</td>';
            print '</tr>';

            // Delivery date planed
            print '<tr><td height="10">';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('DateDeliveryPlanned');
            print '</td>';

            if ($action != 'editdate_livraison') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdate_livraison&amp;id='.$object->id.'">'.img_edit($langs->trans('SetDeliveryDate'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="2">';
            if ($action == 'editdate_livraison')
            {
                print '<form name="setdate_livraison" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
                print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                print '<input type="hidden" name="action" value="setdate_livraison">';
                $form->select_date($object->date_livraison?$object->date_livraison:-1,'liv_','','','',"setdate_livraison");
                print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
                print '</form>';
            }
            else
            {
                print $object->date_livraison ? dol_print_date($object->date_livraison,'daytext') : '&nbsp;';
            }
            print '</td>';

            // Terms of payment
            print '<tr><td height="10">';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('PaymentConditionsShort');
            print '</td>';
            if ($action != 'editconditions' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editconditions&amp;id='.$object->id.'">'.img_edit($langs->trans('SetConditions'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="2">';
            if ($action == 'editconditions')
            {
                $form->form_conditions_reglement($_SERVER['PHP_SELF'].'?id='.$object->id,$object->cond_reglement_id,'cond_reglement_id',1);
            }
            else
            {
                $form->form_conditions_reglement($_SERVER['PHP_SELF'].'?id='.$object->id,$object->cond_reglement_id,'none',1);
            }
            print '</td>';

            print '</tr>';

            // Mode of payment
            print '<tr><td height="10">';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('PaymentMode');
            print '</td>';
            if ($action != 'editmode' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editmode&amp;id='.$object->id.'">'.img_edit($langs->trans('SetMode'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="2">';
            if ($action == 'editmode')
            {
                $form->form_modes_reglement($_SERVER['PHP_SELF'].'?id='.$object->id,$object->mode_reglement_id,'mode_reglement_id');
            }
            else
            {
                $form->form_modes_reglement($_SERVER['PHP_SELF'].'?id='.$object->id,$object->mode_reglement_id,'none');
            }
            print '</td></tr>';

            // Availability
            print '<tr><td height="10">';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('AvailabilityPeriod');
            print '</td>';
            if ($action != 'editavailability' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editavailability&amp;id='.$object->id.'">'.img_edit($langs->trans('SetAvailability'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="2">';
            if ($action == 'editavailability')
            {
                $form->form_availability($_SERVER['PHP_SELF'].'?id='.$object->id,$object->availability_id,'availability_id',1);
            }
            else
            {
                $form->form_availability($_SERVER['PHP_SELF'].'?id='.$object->id,$object->availability_id,'none',1);
            }
            print '</td></tr>';

            // Source
            print '<tr><td height="10">';
            print '<table class="nobordernopadding" width="100%"><tr><td>';
            print $langs->trans('Source');
            print '</td>';
            if ($_GET['action'] != 'editdemandreason' && $object->brouillon) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdemandreason&amp;id='.$object->id.'">'.img_edit($langs->trans('SetDemandReason'),1).'</a></td>';
            print '</tr></table>';
            print '</td><td colspan="2">';
            if ($_GET['action'] == 'editdemandreason')
            {
                $form->form_demand_reason($_SERVER['PHP_SELF'].'?id='.$object->id,$object->demand_reason_id,'demand_reason_id',1);
            }
            else
            {
                $form->form_demand_reason($_SERVER['PHP_SELF'].'?id='.$object->id,$object->demand_reason_id,'none');
            }
            // Removed because using dictionnary is an admin feature, not a user feature. Ther is already the "star" to show info to admin users.
            // This is to avoid too heavy screens and have an uniform look and feel for all screens.
            //print '</td><td>';
            //print '<a href="'.DOL_URL_ROOT.'/admin/dict.php?id=22&origin=order&originid='.$object->id.'">'.$langs->trans("DictionnarySource").'</a>';
            print '</td></tr>';

            // Project
            if ($conf->projet->enabled)
            {
                $langs->load('projects');
                print '<tr><td height="10">';
                print '<table class="nobordernopadding" width="100%"><tr><td>';
                print $langs->trans('Project');
                print '</td>';
                if ($action != 'classify') print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=classify&amp;id='.$object->id.'">'.img_edit($langs->trans('SetProject')).'</a></td>';
                print '</tr></table>';
                print '</td><td colspan="2">';
                //print "$object->id, $object->socid, $object->fk_project";
                if ($action == 'classify')
                {
                    $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, 'projectid');
                }
                else
                {
                    $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, 'none');
                }
                print '</td></tr>';
            }

            // Other attributes
            $parameters=array('colspan' => ' colspan="2"');
            $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
            if (empty($reshook) && ! empty($extrafields->attribute_label))
            {
                foreach($extrafields->attribute_label as $key=>$label)
                {
                    $value=(isset($_POST["options_".$key])?$_POST["options_".$key]:$object->array_options["options_".$key]);
                    print '<tr><td>'.$label.'</td><td colspan="3">';
                    print $extrafields->showInputField($key,$value);
                    print '</td></tr>'."\n";
                }
            }

            // Total HT
            print '<tr><td>'.$langs->trans('AmountHT').'</td>';
            print '<td align="right"><b>'.price($object->total_ht).'</b></td>';
            print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

            // Total TVA
            print '<tr><td>'.$langs->trans('AmountVAT').'</td><td align="right">'.price($object->total_tva).'</td>';
            print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

            // Amount Local Taxes
            if ($mysoc->country_code=='ES')
            {
                if ($mysoc->localtax1_assuj=="1") //Localtax1 RE
                {
                    print '<tr><td>'.$langs->transcountry("AmountLT1",$mysoc->country_code).'</td>';
                    print '<td align="right">'.price($object->total_localtax1).'</td>';
                    print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';
                }
                if ($mysoc->localtax2_assuj=="1") //Localtax2 IRPF
                {
                    print '<tr><td>'.$langs->transcountry("AmountLT2",$mysoc->country_code).'</td>';
                    print '<td align="right">'.price($object->total_localtax2).'</td>';
                    print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';
                }
            }

            // Total TTC
            print '<tr><td>'.$langs->trans('AmountTTC').'</td><td align="right">'.price($object->total_ttc).'</td>';
            print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

            // Statut
            print '<tr><td>'.$langs->trans('Status').'</td>';
            print '<td colspan="2">'.$object->getLibStatut(4).'</td>';
            print '</tr>';

            print '</table><br>';
            print "\n";

            if (! empty($conf->global->MAIN_DISABLE_CONTACTS_TAB))
            {
            	require_once(DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php');
            	$formcompany= new FormCompany($db);

            	$blocname = 'contacts';
            	$title = $langs->trans('ContactsAddresses');
            	include(DOL_DOCUMENT_ROOT.'/core/tpl/bloc_showhide.tpl.php');
            }

            if (! empty($conf->global->MAIN_DISABLE_NOTES_TAB))
            {
            	$blocname = 'notes';
            	$title = $langs->trans('Notes');
            	include(DOL_DOCUMENT_ROOT.'/core/tpl/bloc_showhide.tpl.php');
            }

            /*
             * Lines
             */
            $result = $object->getLinesArray();

            $numlines = count($object->lines);

            if ($conf->use_javascript_ajax && $object->statut == 0)
            {
                include(DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php');
            }

            print '<table id="tablelines" class="noborder" width="100%">';

            // Show object lines
            if (! empty($object->lines)) $object->printObjectLines($action,$mysoc,$soc,$lineid,1,$hookmanager);

            /*
             * Form to add new line
             */
            if ((($object->repair_statut == 1) || ($object->repair_statut == 5)) && ( $user->rights->repair->CreateEstimate || $user->rights->repair->MakeRepair))
            {
                if ($action != 'editline')
                {
                    $var=true;

               	     $object->formAddFreeProduct(1,$mysoc,$soc,$hookmanager);

                    // Add predefined products/services
                    if ($conf->product->enabled || $conf->service->enabled)
                    {
                        $var=!$var;
                        $object->formAddPredefinedProduct(1,$mysoc,$soc,$hookmanager);
                    }

                    $parameters=array();
                    $reshook=$hookmanager->executeHooks('formAddObject',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
                }
            }
            print '</table>';
            print '</div>';


            /*
             * Boutons actions
             */
            if ($action != 'presend')
            {
                if ($user->societe_id == 0 && $action <> 'editline')
                {
                    print '<div class="tabsAction">';

                    // Print Card
                    if ($object->repair_statut == 0 && $user->rights->repair->creer && !(empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)))
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=generatecard">'.$langs->trans('RepairGenerateCard').'</a>';
                    }

                    // Create Estimates
                    if ($object->repair_statut == 0 && $user->rights->repair->CreateEstimate)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=createestimate">'.$langs->trans('RepairCreateEstimate').'</a>';
                    }

                    // Finish Estimates
                    if ($object->repair_statut == 1 && $numlines > 0 && $user->rights->repair->CreateEstimate)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=finishestimate">'.$langs->trans('RepairFinishEstimate').'</a>';
                    }

					// Cancel Estimate
                    if ($object->repair_statut == 1 && $user->rights->repair->creer)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=cancelestimate">'.$langs->trans('RepairCancelEstimate').'</a>';
                    }

					// Validate Estimates
                    if ($object->repair_statut == 2 && $numlines > 0 && $user->rights->repair->ValidateEstimate)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=validateestimate">'.$langs->trans('Validate').'</a>';
                    }

                    // Modify Estimates
                    if ($object->repair_statut == 2 && $user->rights->repair->CreateEstimate)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=modifyestimate">'.$langs->trans('Modify').'</a>';
                    }
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
					// Accepte Estimate
                    if ($object->repair_statut == 3 && $user->rights->repair->ValidateReplies)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=acceptedestimate">'.$langs->trans('RepairAccepteEstimate').'</a>';
                    }

					// Refuse Estimate
                    if ($object->repair_statut == 3 && $user->rights->repair->ValidateReplies)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=refusedestimate">'.$langs->trans('RepairRefuseEstimate').'</a>';
                    }

                    // Unvalid Estimates
                    if ($object->repair_statut == 3 && $user->rights->repair->ValidateEstimate)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=unvalidestimate">'.$langs->trans('Modify').'</a>';
                    }
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    // Make Repair
                    if ($object->repair_statut == 4 && $user->rights->repair->MakeRepair)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=makerepair">'.$langs->trans('MakeRepair').'</a>';
                    }

                    // Unaccept Estimates
                    if ($object->repair_statut == 4 && $user->rights->repair->ValidateEstimate)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=unaccepteestimate">'.$langs->trans('Cancel').'</a>';
                    }

                    // Finish Repair
                    if ($object->repair_statut == 5 && $numlines > 0 && $user->rights->repair->MakeRepair)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=finishrepair">'.$langs->trans('FinishRepair').'</a>';
                    }

					// Cancel Repair
                    if ($object->repair_statut == 5 && $user->rights->repair->MakeRepair)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=cancelrepair">'.$langs->trans('Cancel').'</a>';
                    }

					// Validate Repair
                    if ($object->repair_statut == 6 && $numlines > 0 && $user->rights->repair->ValidateRepair)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=validaterepair">'.$langs->trans('Validate').'</a>';
                    }

                    // Modify Repair
                    if ($object->repair_statut == 6 && $user->rights->repair->MakeRepair)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=modifyrepair">'.$langs->trans('Modify').'</a>';
                    }
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    if ($object->repair_statut == 7 && $user->rights->repair->cloturer)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=close">'.$langs->trans('Close').'</a>';
                    }

                    if ($object->repair_statut == 7 && $user->rights->repair->ValidateRepair)
                    {
                        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=UnvalideRepair">'.$langs->trans('Cancel').'</a>';
                    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                    // Create bill and Classify billed
                    if ($conf->facture->enabled && $object->repair_statut > 3  && ! $object->facturee)
                    {
                        if ($user->rights->facture->creer)
                        {
                            print '<a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture.php?action=create&amp;origin='.$object->element.'&amp;originid='.$object->id.'&amp;socid='.$object->socid.'">'.$langs->trans("CreateBill").'</a>';
                        }

                        if ($user->rights->repair->ValidateReplies && $object->repair_statut > 3)
                        {
                            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=classifybilled">'.$langs->trans("ClassifyBilled").'</a>';
                        }
                    }

                    // Reopen a closed order
                    if (($object->repair_statut == 8) || ($object->repair_statut < 0))
                    {
                        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=reopen">'.$langs->trans('ReOpen').'</a>';
                    }

/*
                    // Edit
                    if ($object->statut == 2 && $user->rights->repair->creer)
                    {
                        print '<a class="butAction" href="fiche.php?id='.$object->id.'&amp;action=modif">'.$langs->trans('Modify').'</a>';
                    }
*/
                    // Send
                    if ($object->repair_statut >= 3)
                    {
                        if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->repair->order_advance->send))
                        {
                            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=presend&amp;mode=init">'.$langs->trans('SendByMail').'</a>';
                        }
                        else print '<a class="butActionRefused" href="#">'.$langs->trans('SendByMail').'</a>';
                    }
/*
                    // Ship
                    $numshipping=0;
                    if ($conf->expedition->enabled)
                    {
                        $numshipping = $object->nb_expedition();

                        if ($object->statut == 7 && $object->getNbOfProductsLines() > 0)
                        {
						    if (($conf->expedition_bon->enabled && $user->rights->expedition->creer)
	                        || ($conf->livraison_bon->enabled && $user->rights->expedition->livraison->creer))
	                        {
                                if ($user->rights->expedition->creer)
                                {
                                    print '<a class="butAction" href="'.DOL_URL_ROOT.'/expedition/shipment.php?id='.$object->id.'">'.$langs->trans('ShipProduct').'</a>';
                                }
                                else
                                {
                                    print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("NotAllowed")).'">'.$langs->trans('ShipProduct').'</a>';
                                }
	                        }
	                        else
	                        {
                                $langs->load("errors");
	                            print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("ErrorModuleSetupNotComplete")).'">'.$langs->trans('ShipProduct').'</a>';
	                        }
                        }
                    }
*/
/*
                    // Reopen a closed order
                    if ($object->statut == 3)
                    {
                        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=reopen">'.$langs->trans('ReOpen').'</a>';
                    }
*/

                    // Clone
                    if ($user->rights->repair->creer)
                    {
                        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;socid='.$object->socid.'&amp;action=clone&amp;object=order">'.$langs->trans("ToClone").'</a>';
                    }

                    // Cancel order
                    if ( ($object->repair_statut >= 0) && ($object->repair_statut < 8) && ($user->rights->repair->annuler) )
                    {
                        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=cancel">'.$langs->trans('Cancel').'</a>';
                    }

                    // Delete order
                    if ($object->repair_statut >= 0 && $object->repair_statut <= 2 && $user->rights->repair->supprimer)
                    {
                        if ($numshipping == 0)
                        {
                            print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete">'.$langs->trans('Delete').'</a>';
                        }
                        else
                        {
                            print '<a class="butActionRefused" href="#" title="'.$langs->trans("ShippingExist").'">'.$langs->trans("Delete").'</a>';
                        }
                    }

                    print '</div>';
                }
                print '<br>';
            }


            if ($action != 'presend')
            {
                print '<table width="100%"><tr><td width="50%" valign="top">';
                print '<a name="builddoc"></a>'; // ancre

                /*
                 * Documents generes
                 *
                 */
                $comref = dol_sanitizeFileName($object->ref);
                $file = $conf->repair->dir_output . '/' . $comref . '/' . $comref . '.pdf';
                $relativepath = $comref.'/'.$comref.'.pdf';
                $filedir = $conf->repair->dir_output . '/' . $comref;
                $urlsource=$_SERVER["PHP_SELF"]."?id=".$object->id;
                $genallowed=$user->rights->repair->creer;
                $delallowed=$user->rights->repair->supprimer;
//TODO Probleme de generation de document

                $somethingshown=$formfile->show_documents('repair',$comref,$filedir,$urlsource,$genallowed,$delallowed,$object->modelpdf,1,0,0,28,0,'','','',$soc->default_lang,$hookmanager);
                /*
                 * Linked object block
                 */
                $somethingshown=$object->showLinkedObjectBlock();

                print '</td><td valign="top" width="50%">';

                // List of actions on element
                include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php');
                $formactions=new FormActions($db);
                $somethingshown=$formactions->showactions($object,'order',$socid);

                print '</td></tr></table>';
            }


            /*
             * Action presend
             *
             */
            if ($action == 'presend')
            {
                $ref = dol_sanitizeFileName($object->ref);
                include_once(DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php');
                $fileparams = dol_most_recent_file($conf->repair->dir_output . '/' . $ref);
                $file=$fileparams['fullname'];

                // Build document if it not exists
                if (! $file || ! is_readable($file))
                {
                    // Define output language
                    $outputlangs = $langs;
                    $newlang='';
                    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
                    if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
                    if (! empty($newlang))
                    {
                        $outputlangs = new Translate("",$conf);
                        $outputlangs->setDefaultLang($newlang);
                    }

                    $result=repair_pdf_create($db, $object, GETPOST('model')?GETPOST('model'):$object->modelpdf, $outputlangs, GETPOST('hidedetails'), GETPOST('hidedesc'), GETPOST('hideref'), $hookmanager);
                    if ($result <= 0)
                    {
                        dol_print_error($db,$result);
                        exit;
                    }
                    $fileparams = dol_most_recent_file($conf->repair->dir_output . '/' . $ref);
                    $file=$fileparams['fullname'];
                }

                print '<br>';
                print_titre($langs->trans('SendRepairByMail'));

                // Cree l'objet formulaire mail
                include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php');
                $formmail = new FormMail($db);
                $formmail->fromtype = 'user';
                $formmail->fromid   = $user->id;
                $formmail->fromname = $user->getFullName($langs);
                $formmail->frommail = $user->email;
                $formmail->withfrom=1;
                $formmail->withto=empty($_POST["sendto"])?1:$_POST["sendto"];
                $formmail->withtosocid=$soc->id;
                $formmail->withtocc=1;
                $formmail->withtoccsocid=0;
                $formmail->withtoccc=$conf->global->MAIN_EMAIL_USECCC;
                $formmail->withtocccsocid=0;
                $formmail->withtopic=$langs->trans('SendRepairRef','__ORDERREF__');
                $formmail->withfile=2;
                $formmail->withbody=1;
                $formmail->withdeliveryreceipt=1;
                $formmail->withcancel=1;
                // Tableau des substitutions
                $formmail->substit['__ORDERREF__']=$object->ref;
                $formmail->substit['__SIGNATURE__']=$user->signature;
                $formmail->substit['__PERSONALIZED__']='';
                // Tableau des parametres complementaires
                $formmail->param['action']='send';
                $formmail->param['models']='order_send';
                $formmail->param['orderid']=$object->id;
                $formmail->param['returnurl']=$_SERVER["PHP_SELF"].'?id='.$object->id;

                // Init list of files
                if (GETPOST("mode")=='init')
                {
                    $formmail->clear_attached_files();
                    $formmail->add_attached_files($file,basename($file),dol_mimetype($file));
                }

                // Show form
                $formmail->show_form();

                print '<br>';
            }
        }
        else
        {
            // Reparation non trouvee
            dol_print_error($db);
        }
    }
}

$db->close();

llxFooter();
?>
