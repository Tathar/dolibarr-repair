<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2007 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
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
 *  \file       htdocs/repair/note.php
 *  \ingroup    repair
 *  \brief      Fiche de notes sur une réparation
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT.'/repair/lib/repair.lib.php');
require_once(DOL_DOCUMENT_ROOT ."/repair/class/repair.class.php");


$langs->load("companies");
$langs->load("bills");
//$langs->load("repairlang@repair");

$id = GETPOST('id','int');
$ref=GETPOST('ref','alpha');
$socid=GETPOST('socid','int');
$action=GETPOST('action','alpha');

// Security check
$socid=0;
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'repair',$id,'');


$object = new Repair($db);
if (! $object->fetch($id, $ref) > 0)
{
	dol_print_error($db);
}


/*
 * Actions
 */

if ($action == 'setnote_public' && $user->rights->repair->creer)
{
	$object->fetch($id);
	$result=$object->update_note_public(dol_html_entity_decode(GETPOST('note_public'), ENT_QUOTES));
	if ($result < 0) dol_print_error($db,$object->error);
}

else if ($action == 'setnote' && $user->rights->repair->creer)
{
	$object->fetch($id);
	$result=$object->update_note(dol_html_entity_decode(GETPOST('note'), ENT_QUOTES));
	if ($result < 0) dol_print_error($db,$object->error);
}

/*
 * View
 */

llxHeader('',$langs->trans('Repair'),'EN:Customers_Repairs|FR:Réparations_Clients|ES:Pedidos de clientes');

$form = new Form($db);

if ($id > 0 || ! empty($ref))
{
	$soc = new Societe($db);
	$soc->fetch($object->socid);

	$head = repair_prepare_head($object);

	dol_fiche_head($head, 'note', $langs->trans("CustomerRepair"), 0, 'repair@repair');

	print '<table class="border" width="100%">';

	// Ref
	print '<tr><td width="25%">'.$langs->trans("Ref").'</td><td colspan="3">';
	print $form->showrefnav($object,'ref','',1,'ref','ref');
	print "</td></tr>";

	// Ref Réparation client
	print '<tr><td>';
	print '<table class="nobordernopadding" width="100%"><tr><td nowrap>';
	print $langs->trans('RefCustomer').'</td><td align="left">';
	print '</td>';
	print '</tr></table>';
	print '</td><td colspan="3">';
	print $object->ref_client;
	print '</td>';
	print '</tr>';

	// Customer
	print "<tr><td>".$langs->trans("Company")."</td>";
	print '<td colspan="3">'.$soc->getNomUrl(1).'</td></tr>';

	print "</table>";

	print '<br>';

	include(DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php');

	print '</div>';
}


llxFooter();
$db->close();
?>
