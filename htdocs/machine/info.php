<?php
/* Copyright (C) 2005-2006 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
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
 *      \file       htdocs/repair/info.php
 *      \ingroup    repair
 *		\brief      Page des informations d'une repair
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT."/repair/class/repair.class.php");
require_once(DOL_DOCUMENT_ROOT."/repair/lib/repair.lib.php");

if (!$user->rights->repair->read)	accessforbidden();

//$langs->load("repairlang@repair");
$langs->load("sendings");

// Security check
$socid=0;
$comid = isset($_GET["id"])?$_GET["id"]:'';
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'repair',$comid,'');



/*
 * View
 */

llxHeader('',$langs->trans('Repair'),'EN:Customers_Repairs|FR:Réparation_Clients|ES:Pedidos de clientes');

$repair = new Repair($db);
$repair->fetch($_GET["id"]);
$repair->info($_GET["id"]);
$soc = new Societe($db);
$soc->fetch($repair->socid);

$head = repair_prepare_head($repair);
dol_fiche_head($head, 'info', $langs->trans("CustomerRepair"), 0, 'repair@repair');


print '<table width="100%"><tr><td>';
dol_print_object_info($repair);
print '</td></tr></table>';

print '</div>';


$db->close();

llxFooter();
?>
