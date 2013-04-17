<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville   <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur    <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo  <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin          <regis@dolibarr.fr>
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
 *	\file       htdocs/repair/liste.php
 *	\ingroup    repair
 *	\brief      Page to list orders
 */


require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
require_once(DOL_DOCUMENT_ROOT ."/repair/class/repair.class.php");

$conf->repair->client->warning_delay=(isset($conf->global->MAIN_DELAY_REPAIRS_TO_PROCESS)?$conf->global->MAIN_DELAY_REPAIRS_TO_PROCESS:7)*24*60*60;

//$langs->load('orders');
$langs->load('repair@repair');
$langs->load('deliveries');
$langs->load('companies');

$orderyear=GETPOST("orderyear","int");
$ordermonth=GETPOST("ordermonth","int");
$deliveryyear=GETPOST("deliveryyear","int");
$deliverymonth=GETPOST("deliverymonth","int");
$sref=GETPOST('sref','alpha');
$sref_client=GETPOST('sref_client','alpha');
$snom=GETPOST('snom','alpha');
$sall=GETPOST('sall');
$socid=GETPOST('socid','int');
$trademark=GETPOST('trademark','alpha');
$model=GETPOST('model','alpha');

// Security check
$id = (GETPOST('orderid')?GETPOST('orderid'):GETPOST('id','int'));
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'repair', $id,'');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='c.rowid';
if (! $sortorder) $sortorder='DESC';
$limit = $conf->liste_limit;

$viewstatut=GETPOST('viewstatut');


/*
 * View
 */

$now=dol_now();

$form = new Form($db);
$formfile = new FormFile($db);
$companystatic = new Societe($db);

llxHeader();

$sql = 'SELECT s.nom, s.rowid as socid, s.client, c.rowid, c.ref, c.total_ht, c.ref_client,';
$sql.= ' c.date_valid, c.date_valid, c.date_repair, c.date_livraison, c.fk_statut, c.on_process, c.facture as facturee,';
$sql.= ' mm.model, mt.trademark';
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe as s';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'repair as c ON c.fk_soc = s.rowid';
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine as m ON c.fk_machine = m.rowid";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_model as mm ON m.fk_model = mm.rowid";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_trademark as mt ON mm.fk_trademark = mt.rowid";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= ' WHERE';
$sql.= ' c.entity = '.$conf->entity;
if ($socid)	$sql.= ' AND s.rowid = '.$socid;
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($sref)
{
	$sql.= " AND c.ref LIKE '%".$db->escape($sref)."%'";
}
if ($sall)
{
	$sql.= " AND (c.ref LIKE '%".$db->escape($sall)."%' OR c.note LIKE '%".$db->escape($sall)."%')";
}
if ($viewstatut <> '')
{
	if ($viewstatut == -1 ) // annulee
	{
		$sql.= ' AND c.fk_statut = -1'; 
	}
	if ($viewstatut == 0 ) // brouillon
	{
		$sql.= ' AND c.fk_statut = 0 AND c.on_process = 0'; 
	}
	if ($viewstatut == 1) // en cours
	{
		$sql.= ' AND c.fk_statut = 0 AND c.on_process = 1'; 
	}
	if ($viewstatut == 2) // terminee
	{
		$sql.= ' AND c.fk_statut = 1'; 
	}
	if ($viewstatut == 3) // validee
	{
		$sql.= ' AND c.fk_statut = 2'; 
	}
	if ($viewstatut == 4) //a facturer
	{
		$sql.= ' AND c.fk_statut = 2 AND c.facture = 0'; // need to create invoice
	}
	if ($viewstatut == 5) // cloturee
	{
		$sql.= ' AND c.fk_statut = 3 AND c.facture = 1'; // invoice created
	}
/*	if ($viewstatut == -2)	// To process
	{
		//$sql.= ' AND c.fk_statut IN (1,2,3) AND c.facture = 0';
		$sql.= " AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))";    // If status is 2 and facture=1, it must be selected
	}*/
}
if ($ordermonth > 0)
{
	$sql.= " AND date_format(c.date_repair, '%Y-%m') = '".$orderyear."-".$ordermonth."'";    // TODO do not use date_format but a between
}
if ($orderyear > 0)
{
	$sql.= " AND date_format(c.date_repair, '%Y') = '".$orderyear."'";
}
if ($deliverymonth > 0)
{
	$sql.= " AND date_format(c.date_livraison, '%Y-%m') = '".$deliveryyear."-".$deliverymonth."'";
}
if ($deliveryyear > 0)
{
	$sql.= " AND date_format(c.date_livraison, '%Y') = '".$deliveryyear."'";
}
if (!empty($snom))
{
	$sql.= ' AND s.nom LIKE \'%'.$db->escape($snom).'%\'';
}
if (!empty($sref_client))
{
	$sql.= ' AND c.ref_client LIKE \'%'.$db->escape($sref_client).'%\'';
}
if (!empty($trademark))
{
	$sql.= ' AND mt.trademark LIKE \'%'.$db->escape($trademark).'%\'';
}
if (!empty($model))
{
	$sql.= ' AND mm.model LIKE \'%'.$db->escape($model).'%\'';
}

$sql.= ' ORDER BY '.$sortfield.' '.$sortorder;
$sql.= $db->plimit($limit + 1,$offset);

dol_syslog("/repair/list.php sql=".$sql);
$resql = $db->query($sql);

if ($resql)
{
	if ($socid)
	{
		$soc = new Societe($db);
		$soc->fetch($socid);
		$title = $langs->trans('ListOfRepairs') . ' - '.$soc->nom;
	}
	else
	{
		$title = $langs->trans('ListOfRepairs');
	}
	
	if (strval($viewstatut) == '0')
		$nameStats = ' - '.Repair::LibStatut(0, 0, 0, 0);
	if ($viewstatut == 1 )
		$nameStats = ' - '.Repair::LibStatut(0, 1, 0, 0);
	if (($viewstatut >= 2 ) && ($viewstatut <= 4))
		$nameStats = ' - '.Repair::LibStatut($viewstatut - 1, 0, 0, 0);
	if ($viewstatut == 5 )
		$nameStats = ' - '.Repair::LibStatut(3, 0, 1, 0);
	if ($viewstatut == -1)
		$nameStats = ' - '.Repair::LibStatut($viewstatut, 0, 0, 0);
	$title.=$nameStats;

	$num = $db->num_rows($resql);
	print_barre_liste($title, $page, 'liste.php','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,$sortfield,$sortorder,'',$num);
	$i = 0;
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans('Ref'),'liste.php','c.ref','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Company'),'liste.php','s.nom','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('RefCustomerRepair'),'liste.php','c.ref_client','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'',$sortfield,$sortorder);
//<Tahtar>
	print_liste_field_titre($langs->trans('MachineTrademark'),'liste.php','mt.trademark','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('MachineModel'),'liste.php','mm.model','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'',$sortfield,$sortorder);
//</Tathar>
	print_liste_field_titre($langs->trans('RepairDate'),'liste.php','c.date_repair','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('DeliveryDate'),'liste.php','c.date_livraison','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Status'),'liste.php','c.repair_statut','','&amp;socid='.$socid.'&amp;viewstatut='.$viewstatut,'align="right"',$sortfield,$sortorder);
	print '</tr>';
	// Lignes des champs de filtre
	print '<form method="get" action="liste.php">';
	print '<tr class="liste_titre">';
	print '<input class="hide" type="text" name="viewstatut" value="'.$viewstatut.'">';
	print '<input class="hide" type="int" name="orderyear" value="'.$orderyear.'">';
	print '<input class="hide" type="int" name="ordermonth" value="'.$ordermonth.'">';
	print '<input class="hide" type="int" name="deliveryyear" value="'.$deliveryyear.'">';
	print '<input class="hide" type="int" name="deliverymonth" value="'.$deliverymonth.'">';
	print '<td class="liste_titre">';
	print '<input class="flat" size="13" type="text" name="sref" value="'.$sref.'">';
	print '</td><td class="liste_titre" align="left">';
	print '<input class="flat" type="text" name="snom" value="'.$snom.'">';
	print '</td><td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="10" name="sref_client" value="'.$sref_client.'">';
//<Tahtar>
	print '</td><td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="10" name="trademark" value="'.$trademark.'">';
	print '</td><td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="10" name="model" value="'.$model.'">';
//</Tahtar>
	print '</td><td class="liste_titre">&nbsp;';
	print '</td><td class="liste_titre">&nbsp;';
	print '</td><td align="right" class="liste_titre">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png"  value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td></tr>';
	print '</form>';
	$var=True;
	$generic_repair = new Repair($db);
	while ($i < min($num,$limit))
	{
		$objp = $db->fetch_object($resql);
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td nowrap="nowrap">';

		$generic_repair->id=$objp->rowid;
		$generic_repair->ref=$objp->ref;

		print '<table class="nobordernopadding"><tr class="nocellnopadd">';
		print '<td class="nobordernopadding" nowrap="nowrap">';
		print $generic_repair->getNomUrl(1,$objp->fk_statut);
		print '</td>';

		print '<td width="20" class="nobordernopadding" nowrap="nowrap">';
		if (($objp->fk_statut >= 0) && ($objp->fk_statut < 3) && $db->jdate($objp->date_repair) < ($now - $conf->repair->client->warning_delay)) print img_picto($langs->trans("Late"),"warning");
		print '</td>';

		print '<td width="16" align="right" class="nobordernopadding">';
		$filename=dol_sanitizeFileName($objp->ref);
		$filedir=$conf->repair->dir_output . '/' . dol_sanitizeFileName($objp->ref);
		$urlsource=$_SERVER['PHP_SELF'].'?id='.$objp->rowid;
		$formfile->show_documents('repair',$filename,$filedir,$urlsource,'','','',1,'',1);
		print '</td></tr></table>';

		print '</td>';

		// Company
		$companystatic->id=$objp->socid;
		$companystatic->nom=$objp->nom;
		$companystatic->client=$objp->client;
		print '<td>';
//		print $companystatic->getNomUrl(1,'customer',24);
		print $generic_repair->getThirdpartyUrl(1,$objp->socid,24);
		print '</td>';

		// Ref_client
		print '<td>'.$objp->ref_client.'</td>';

		// TradeMark
		print '<td><a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$orderyear.'&ordermonth='.$ordermonth.'&deliveryyear='.$deliveryyear.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$objp->trademark.'&model='.$model.'">'.dol_trunc($objp->trademark,18).'</a></td>';


		// Model
		print '<td><a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$orderyear.'&ordermonth='.$ordermonth.'&deliveryyear='.$deliveryyear.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$objp->model.'">'.dol_trunc($objp->model,18).'</a></td>';

		// Repair date
		$y = dol_print_date($db->jdate($objp->date_repair),'%Y');
		$m = dol_print_date($db->jdate($objp->date_repair),'%m');
		$ml = dol_print_date($db->jdate($objp->date_repair),'%B');
		$d = dol_print_date($db->jdate($objp->date_repair),'%d');

		print '<td align="right">';
		print $d;
		print ' <a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$y.'&ordermonth='.$m.'&deliveryyear='.$deliveryyear.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$model.'">'.$ml.'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$y./*'&ordermonth='.$ordermonth.*/'&deliveryyear='.$deliveryyear.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$model.'">'.$y.'</a>';
		print '</td>';

		// Delivery date
		$y = dol_print_date($db->jdate($objp->date_livraison),'%Y');
		$m = dol_print_date($db->jdate($objp->date_livraison),'%m');
		$ml = dol_print_date($db->jdate($objp->date_livraison),'%B');
		$d = dol_print_date($db->jdate($objp->date_livraison),'%d');

		print '<td align="right">';
		print $d;
		print ' <a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$orderyear.'&ordermonth='.$ordermonth.'&deliveryyear='.$y.'&deliverymonth='.$m.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$model.'">'.$ml.'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$viewstatut.'&sref='.$sref.'&orderyear='.$orderyear./*'&ordermonth='.$ordermonth.*/'&deliveryyear='.$y.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$model.'">'.$y.'</a>';
		print '</td>';

		// Statut

		print '<td align="right" nowrap="nowrap"><a href="'.$_SERVER['PHP_SELF'].'?viewstatut='.$linkstatus.'&sref='.$sref.'&orderyear='.$orderyear.'&ordermonth='.$ordermonth.'&deliveryyear='.$deliveryyear.'&deliverymonth='.$deliverymonth.'&snom='.$snom.'&sref_client='.$sref_client.'&trademark='.$trademark.'&model='.$model.'">'.$generic_repair->LibStatut($objp->fk_statut,$objp->on_process,$objp->facturee,5).'</a></td>';
		print '</tr>';

		$total = $total + $objp->price;
		$subtotal = $subtotal + $objp->price;
		$i++;
	}
	print '</table>';
	$db->free($resql);
}
else
{
	print dol_print_error($db);
}

$db->close();

llxFooter();

?>
