<?php
/* Copyright (C) 2003-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2012      Juanjo Menent		<jmenent@2byte.es>
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
 *		\defgroup   machine     Module orders
 *		\brief      Module pour gerer le suivi des machines
 *		\file       htdocs/machine/core/modules/modMachine.class.php
 *		\ingroup    machine
 *		\brief      Fichier de description et activation du module Machine
 */

include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");


/**
 *	Classe de description et activation du module Machine
 */
class modMachine extends DolibarrModules
{

	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function modMachine($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 12400;

		$this->family = "crm";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "Gestion des machines";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '0.1';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto='machine@machine';

		// Data directories to create when module is enabled
		$this->dirs = array("/machine/temp");

		// Config pages
//		$this->config_page_url = array("machine.php@machine");

		// Dependancies
		$this->depends = array("modSociete");
//		$this->requiredby = array("modExpedition");
		$this->requiredby = array("modRepair");
		$this->conflictwith = array();
		$this->langfiles = array("bills","companies","products","machine@machine");

		// Constantes
		$this->const = array();
		$r=0;

		//Tabs ajout d'onglet dans les fiche client, produit, etc
		$this->tabs = array('thirdparty:+machine:Machine:@machine:$user->rights->machine->read:/machine/thirdparty.php?id=__ID__');

		// Permissions
		$this->rights = array();
		$this->rights_class = 'machine';

		$r=0;
/*
		$r++;
		$this->rights[$r][0] = 12401;
		$this->rights[$r][1] = 'Lire les machines';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'lire';
*/
		$r++;
		$this->rights[$r][0] = 12401;
		$this->rights[$r][1] = 'Lire les machines';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
/*
		$r++;
		$this->rights[$r][0] = 12402;
		$this->rights[$r][1] = 'Créer les machines';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'creer';
*/
		$r++;
		$this->rights[$r][0] = 12402;
		$this->rights[$r][1] = 'Créer les machines';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'create';

		$r++;
		$this->rights[$r][0] = 12408;
		$this->rights[$r][1] = 'Envoyer les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'machine_advance';
        $this->rights[$r][5] = 'send';

		$r++;
		$this->rights[$r][0] = 12409;
		$this->rights[$r][1] = 'Cloturer les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'cloturer';

		$r++;
		$this->rights[$r][0] = 124010;
		$this->rights[$r][1] = 'Annuler les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'annuler';

		$r++;
		$this->rights[$r][0] = 124010;
		$this->rights[$r][1] = 'Annuler les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'cancel';

		$r++;
		$this->rights[$r][0] = 12411;
		$this->rights[$r][1] = 'Supprimer les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'supprimer';

		$r++;
		$this->rights[$r][0] = 12411;
		$this->rights[$r][1] = 'Supprimer les machines';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';

		$r++;
		$this->rights[$r][0] = 12412;
		$this->rights[$r][1] = 'Exporter les machines et attributs';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'machine';
		$this->rights[$r][5] = 'export';


//tathar
		//Menus ajout des menus gauche et superieur

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		$this->menu[$r]=array('fk_menu'=>0,
													'type'=>'top',
													'titre'=>'Machines',
													'mainmenu'=>'machine',
													'url'=>'/machine/index.php?leftmenu=machine',
													'langs'=>'machine@machine',
													'position'=>100,
													'perms'=>'$user->rights->machine->read',
													'enabled'=>'$conf->machine->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=0',
													'type'=>'left',
													'titre'=>'Machines',
													'mainmenu'=>'machine',
													'url'=>'/machine/index.php?leftmenu=machine',
													'langs'=>'machine@machine',
													'position'=>100,
													'perms'=>'$user->rights->machine->read',
													'enabled'=>'$conf->machine->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

//for repair
		$this->menu[$r]=array('fk_menu'=>'r=0',
													'type'=>'left',
													'titre'=>'Repairs',
													'mainmenu'=>'machine',
													'url'=>'/repair/index.php?leftmenu=repair',
													'langs'=>'repair@repair',
													'position'=>100,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
		$this->menu[$r]=array('fk_menu'=>'r=2',
													'type'=>'left',
													'titre'=>'NewRepair',
													'mainmenu'=>'machine',
													'url'=>'/societe/societe.php?leftmenu=repair',
													'langs'=>'societe',
													'position'=>101,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=2',
													'type'=>'left',
													'titre'=>'List',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair',
													'langs'=>'repair@repair',
													'position'=>102,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairDraft',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=0',
													'langs'=>'repair@repair',
													'position'=>113,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairOnProcess',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=1',
													'langs'=>'repair@repair',
													'position'=>114,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
//
		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairValidated',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=2',
													'langs'=>'repair@repair',
													'position'=>115,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairSentShort',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=3',
													'langs'=>'repair@repair',
													'position'=>116,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;
//
		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairToBill',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=4',
													'langs'=>'repair@repair',
													'position'=>117,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairProcessed',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=5',
													'langs'=>'repair@repair',
													'position'=>118,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=4',
													'type'=>'left',
													'titre'=>'StatusRepairCanceled',
													'mainmenu'=>'machine',
													'url'=>'/repair/liste.php?leftmenu=repair&viewstatut=-1',
													'langs'=>'repair@repair',
													'position'=>121,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'r=2',
													'type'=>'left',
													'titre'=>'Statistics',
													'mainmenu'=>'machine',
													'url'=>'/repair/stats/index.php?leftmenu=repair',
													'langs'=>'repair@repair',
													'position'=>130,
													'perms'=>'$user->rights->repair->lire',
													'enabled'=>'$conf->repair->enabled',
													'target'=>'',
													'user'=>2);
		$r++;



        // Dictionnaries
        if (! isset($conf->machine->enabled)) $conf->machine->enabled=0;		// This is to avoid warnings
        $this->dictionnaries=array(
            'langs'=>'machine@machine',
            'tabname'=>array(MAIN_DB_PREFIX."c_machine_type"),			// List of tables we want to see into dictonnary editor
            'tablib'=>array("Machine Type"),							// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'c_machine_type as f'),			// Request to select fields
            'tabsqlsort'=>array("label ASC"),							// Sort order
            'tabfield'=>array("code,label"),							// List of fields (result of select to show dictionnary)
            'tabfieldvalue'=>array("code,label"),						// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label"),						// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid"),									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->machine->enabled)					// Condition to show each dictionnary
        );

		// Exports
		//--------
		$r=0;

		$r++;
		$this->export_code[$r]=$this->rights_class.'_'.$r;
		$this->export_label[$r]='CustomersMachinesAndMachinesLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_permission[$r]=array(array("machine","machine","export"));
		$this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.cp'=>'Zip','s.ville'=>'Town','s.fk_pays'=>'Country','s.tel'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','c.rowid'=>"Id",'c.ref'=>"Ref",'c.ref_client'=>"RefClient",'c.fk_soc'=>"IdCompany",'c.date_creation'=>"DateCreation",'c.date_machine'=>"DateOrder",'c.amount_ht'=>"Amount",'c.remise_percent'=>"GlobalDiscount",'c.total_ht'=>"TotalHT",'c.total_ttc'=>"TotalTTC",'c.facture'=>"OrderShortStatusInvoicee",'c.fk_statut'=>'Status','c.note'=>"Note",'c.date_livraison'=>'DeliveryDate','cd.rowid'=>'LineId','cd.description'=>"LineDescription",'cd.product_type'=>'TypeOfLineServiceOrProduct','cd.tva_tx'=>"LineVATRate",'cd.qty'=>"LineQty",'cd.total_ht'=>"LineTotalHT",'cd.total_tva'=>"LineTotalVAT",'cd.total_ttc'=>"LineTotalTTC",'p.rowid'=>'ProductId','p.ref'=>'ProductRef','p.label'=>'Label');
		$this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.cp'=>'company','s.ville'=>'company','s.fk_pays'=>'company','s.tel'=>'company','s.siren'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.siret'=>'company','c.rowid'=>"order",'c.ref'=>"order",'c.ref_client'=>"order",'c.fk_soc'=>"order",'c.date_creation'=>"order",'c.date_machine'=>"order",'c.amount_ht'=>"order",'c.remise_percent'=>"order",'c.total_ht'=>"order",'c.total_ttc'=>"order",'c.facture'=>"order",'c.fk_statut'=>"order",'c.note'=>"order",'c.date_livraison'=>"order",'cd.rowid'=>'order_line','cd.description'=>"order_line",'cd.product_type'=>'order_line','cd.tva_tx'=>"order_line",'cd.qty'=>"order_line",'cd.total_ht'=>"order_line",'cd.total_tva'=>"order_line",'cd.total_ttc'=>"order_line",'p.rowid'=>'product','p.ref'=>'product','p.label'=>'product');

		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'machine as c, '.MAIN_DB_PREFIX.'societe as s, '.MAIN_DB_PREFIX.'machinedet as cd)';
		$this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (cd.fk_product = p.rowid)';
		$this->export_sql_end[$r] .=' WHERE c.fk_soc = s.rowid AND c.rowid = cd.fk_machine';
		$this->export_sql_end[$r] .=' AND c.entity = '.$conf->entity;
	}


	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $conf,$langs;

		$this->load_tables();

		// Permissions
		$this->remove($options);

		//ODT template
		$src=DOL_DOCUMENT_ROOT.'/install/doctemplates/machines/template_machine.odt';
		$dirodt=DOL_DATA_ROOT.'/doctemplates/machines';
		$dest=$dirodt.'/template_machine.odt';

		if (file_exists($src) && ! file_exists($dest))
		{
			require_once(DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php');
			dol_mkdir($dirodt);
			$result=dol_copy($src,$dest,0,0);
			if ($result < 0)
			{
				$langs->load("errors");
				$this->error=$langs->trans('ErrorFailToCopyFile',$src,$dest);
				return 0;
			}
		}

		$sql = array(
				"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = '".$this->const[0][2]."' AND entity = ".$conf->entity,
				"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('".$this->const[0][2]."','machine',".$conf->entity.")"
		);

		 return $this->_init($sql,$options);
	}


    /**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
     */
    function remove($options='')
    {
		$sql = array();

		return $this->_remove($sql,$options);
    }


	/**
	 *		Create tables, keys and data required by module
	 * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 		and create data machines must be stored in directory /mymodule/sql/
	 *		This function is called by this->init
	 *
	 * 		@return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		return $this->_load_tables('/machine/sql/');
	}

}
?>
