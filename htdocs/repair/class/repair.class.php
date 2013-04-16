<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2010-2011 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2011      Jean Heimburger      <jean@tiaris.info>
 * Copyright (C) 2013      Pierre-Emmanuel DOUET	<tathar.dev@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU  *General Public License as published by
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
 *  \file       htdocs/repair/class/repair.class.php
 *  \ingroup    repair
 *  \brief      Fichier des classes de repairs
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/commonorder.class.php';
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/machine/class/machine.class.php");

/**
 *  \class      Repair
 *  \brief      Class to manage customers orders
 */
class Repair extends CommonOrder
{
    public $element='repair';
    public $table_element='repair';
    public $table_element_line = 'repairdet';
    public $class_element_line = 'RepairLine';
    public $fk_element = 'fk_repair';
    protected $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

    var $id;

    var $socid;		// Id client
    var $client;		// Objet societe client (a charger par fetch_client)

    var $ref;
    var $ref_client;
    var $ref_ext;
    var $ref_int;
    var $contactid;
    var $fk_project;
//	var $fk_machine;     //link to machine table
	var $trademark;	
	var $type_id;
	var $model;
	var $n_model;
	var $serial_num;
	var $breakdown;
	var $support_id;
//TODO fk_machine_lend 
	var $accessory;
	var $statut;		// -1=Canceled, 0=Draft, 1=Validated, (2=Accepted/On process not managed for customer orders), 3=Closed (Sent/Received, billed or not)
	var $on_process;		// 0=Waiting, 1=On process

    var $facturee;		// Facturee ou non
    var $brouillon;
    var $cond_reglement_id;
    var $cond_reglement_code;
    var $mode_reglement_id;
    var $mode_reglement_code;
    var $availability_id;
    var $availability_code;
    var $demand_reason_id;
    var $demand_reason_code;
    var $fk_delivery_address;
    var $adresse;
    var $date;				// Date repair
    var $date_repair;		// Date repair (deprecated)
    var $date_livraison;	// Date livraison souhaitee
    var $fk_remise_except;
    var $remise_percent;
    var $total_ht;			// Total net of tax
    var $total_ttc;			// Total with tax
    var $total_tva;			// Total VAT
    var $total_localtax1;   // Total Local tax 1
    var $total_localtax2;   // Total Local tax 2
    var $remise_absolue;
    var $modelpdf;
    var $info_bits;
    var $rang;
    var $special_code;
    var $source;			// Origin of order
    var $note;				// deprecated
    var $note_private;
    var $note_public;
    var $extraparams=array();

    var $origin;
    var $origin_id;
    var $linked_objects=array();

    var $user_author_id;

    var $lines = array();

    // Pour board
    var $nbtodo;
    var $nbtodolate;


    /**
     *	Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;

        $this->remise = 0;
        $this->remise_percent = 0;

        $this->products = array();
    }

    /**
	 *  Returns the reference to the following non used Repair depending on the active numbering module
	 *  defined into REPAIR_ADDON
	 *
	 *  @param	Societe		$soc  	Object thirdparty
	 *  @return string      		Repair free reference
	 */
    function getNextNumRef($soc)
    {
        global $db, $langs, $conf;
        $langs->load("repairlang@repair");

        $dir = DOL_DOCUMENT_ROOT . "/repair/core/modules/repair";

        if (! empty($conf->global->REPAIR_ADDON))
        {
            $file = $conf->global->REPAIR_ADDON.".php";

            // Chargement de la classe de numerotation
            $classname = $conf->global->REPAIR_ADDON;

            $result=include_once $dir.'/'.$file;
            if ($result)
            {
                $obj = new $classname();
                $numref = "";
                $numref = $obj->getNextValue($soc,$this);

                if ( $numref != "")
                {
                    return $numref;
                }
                else
                {
                    dol_print_error($db,"Repair::getNextNumRef ".$obj->error);
                    return "";
                }
            }
            else
            {
                print $langs->trans("Error")." ".$langs->trans("Error_REPAIR_ADDON_NotDefined")." = ".$dir.'/'.$file;
                return "";
            }
        }
        else
        {
            print $langs->trans("Error")." ".$langs->trans("Error_REPAIR_ADDON_NotDefined");
            return "";
        }
    }


    /**
     *	Validate Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function valid($user, $idwarehouse=0)
    {
        global $conf,$langs;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

        $error=0;

        // Protection
        if ($this->statut == 1)
        {
			dol_syslog(get_class($this)."::valid no draft status", LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->ValidateRepair)
        {
            $this->error='Permission denied';
            dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
            return -1;
        }

		$now=dol_now();

        $this->db->begin();

        // Definition du nom de module de numerotation de reparation
        $soc = new Societe($this->db);
        $soc->fetch($this->socid);

        // Class of company linked to order
        $result=$soc->set_as_client();

        // Define new ref
        if (! $error && (preg_match('/^[\(]?PROV/i', $this->ref)))
        {
            $num = $this->getNextNumRef($soc);
        }
        else
        {
            $num = $this->ref;
        }

        // Validate
        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET ref = '".$num."',";
        $sql.= " fk_statut = 1,";
        $sql.= " on_process = 0,";
        $sql.= " date_valid='".$this->db->idate($now)."',";
        $sql.= " fk_user_valid = ".$user->id;
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::valid() sql=".$sql);
		$resql=$this->db->query($sql);
		if (! $resql)
        {
            dol_syslog(get_class($this)."::valid() Echec update - 10 - sql=".$sql, LOG_ERR);
            dol_print_error($this->db);
            $error++;
        }
//TODO Verifier la gestion des stock
        if (! $error)
        {
            // If stock is incremented on validate order, we must increment it
            if ($result >= 0 && ! empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
            {
                require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
                $langs->load("agenda");

                // Loop on each line
                $cpt=count($this->lines);
                for ($i = 0; $i < $cpt; $i++)
                {
                    if ($this->lines[$i]->fk_product > 0)
                    {
                        $mouvP = new MouvementStock($this->db);
                        // We decrement stock of product (and sub-products)
                        $result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("RepairValidatedInDolibarr",$num));
                        if ($result < 0) { $error++; }
                    }
                }
            }
        }

        if (! $error)
        {
            $this->oldref='';

            // Rename directory if dir was a temporary ref
            if (preg_match('/^[\(]?PROV/i', $this->ref))
            {
                // On renomme repertoire ($this->ref = ancienne ref, $numfa = nouvelle ref)
                // afin de ne pas perdre les fichiers attaches
                $comref = dol_sanitizeFileName($this->ref);
                $snum = dol_sanitizeFileName($num);
                $dirsource = $conf->repair->dir_output.'/'.$comref;
                $dirdest = $conf->repair->dir_output.'/'.$snum;
                if (file_exists($dirsource))
                {
                    dol_syslog(get_class($this)."::valid() rename dir ".$dirsource." into ".$dirdest);

                    if (@rename($dirsource, $dirdest))
                    {
                        $this->oldref = $comref;

                        dol_syslog("Rename ok");
                        // Suppression ancien fichier PDF dans nouveau rep
                        dol_delete_file($conf->repair->dir_output.'/'.$snum.'/'.$comref.'*.*');
                    }
                }
            }
        }

        // Set new ref and current status
        if (! $error)
        {
            $this->ref = $num;
            $this->statut = 1;
        }

        if (! $error)
        {
            // Appel des triggers
            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('ORDER_VALIDATE',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }

        if (! $error)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            $this->error=$this->db->lasterror();
            return -1;
        }
    }

    /**
     *	Set draft status
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function set_draft($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->statut <= 0)
        {
            return 0;
        }

        if (! $user->rights->repair->valider)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET fk_statut = 0,";
        $sql.= " on_process = 0";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::set_draft sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            // If stock is decremented on validate order, we must reincrement it
            if (! empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
            {
                require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
                $langs->load("agenda");

                $num=count($this->lines);
                for ($i = 0; $i < $num; $i++)
                {
                    if ($this->lines[$i]->fk_product > 0)
                    {
                        $mouvP = new MouvementStock($this->db);
                        // We increment stock of product (and sub-products)
                        $result=$mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("RepairBackToDraftInDolibarr",$this->ref));
                        if ($result < 0) { $error++; }
                    }
                }

                if (!$error)
                {
                    $this->statut=0;
					$this->on_process=0;
                    $this->db->commit();
                    return $result;
                }
                else
                {
                    $this->error=$mouvP->error;
                    $this->db->rollback();
                    return $result;
                }
            }

            $this->statut=0;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *	Tag the order as validated (opened)
     *	Function used when order is reopend after being closed.
     *
     *	@param      User	$user       Object user that change status
     *	@return     int         		<0 if KO, 0 if nothing is done, >0 if OK
     */
    function set_reopen($user)
    {
        global $conf,$langs;
        $error=0;

        if ($this->statut != 3)
        {
            return 0;
        }

        $this->db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
        $sql.= ' SET fk_statut=1, facture=0';
        $sql.= ' WHERE rowid = '.$this->id;

        dol_syslog("Repair::set_reopen sql=".$sql);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            // Appel des triggers
            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('REPAIR_REOPEN',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }
        else
        {
            $error++;
            $this->error=$this->db->error();
            dol_print_error($this->db);
        }

        if (! $error)
        {
            $this->statut = 1;
        	$this->billed = 0;
			$this->facturee = 0; // deprecated

            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *  Close order
     *
     * 	@param      User	$user       Objet user that close
     *	@return		int					<0 if KO, >0 if OK
     */
    function cloture($user)
    {
        global $conf, $langs;

        $error=0;

        if ($user->rights->repair->cloturer)
        {
            $this->db->begin();

            $now=dol_now();

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
            $sql.= ' SET fk_statut = 3,';
            $sql.= ' on_process = 0,';
            $sql.= ' fk_user_cloture = '.$user->id.',';
            $sql.= ' date_cloture = '.$this->db->idate($now);
            $sql.= ' WHERE rowid = '.$this->id.' AND fk_statut > 0';
        	dol_syslog(get_class($this)."::cloture sql=".$sql, LOG_DEBUG);
            if ($this->db->query($sql))
            {
                // Appel des triggers
                include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('REPAIR_CLOSE',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers

                if (! $error)
                {
            		$this->statut=3;
            		$this->on_process=0;
            		$this->db->commit();
                    return 1;
                }
                else
                {
                    $this->db->rollback();
                    return -1;
                }
            }
            else
            {
                $this->error=$this->db->lasterror();
                dol_syslog($this->error, LOG_ERR);

                $this->db->rollback();
                return -1;
            }
        }
		else
		{
            $this->error='Permission denied';
            return -1;
		}
    }

    /**
     *	Cancel Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function cancel($idwarehouse=-1)
    {
        global $conf,$user,$langs;

        $error=0;

        if (! $user->rights->repair->MakeRepair)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET fk_statut = -1,";
        $sql.= " on_process = 0";
        $sql.= " WHERE rowid = ".$this->id;
		$sql.= " AND fk_statut = 1";

        dol_syslog(get_class($this)."::cancel sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
			// If stock is decremented on validate order, we must reincrement it
			if (! empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
			{
				require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
				$langs->load("agenda");

				$num=count($this->lines);
				for ($i = 0; $i < $num; $i++)
				{
					if ($this->lines[$i]->fk_product > 0)
					{
						$mouvP = new MouvementStock($this->db);
						// We increment stock of product (and sub-products)
						$result=$mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderCanceledInDolibarr",$this->ref));
						if ($result < 0) {
							$error++;
						}
					}
				}
			}

			if (! $error)
			{
				// Appel des triggers
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('REPAIR_CANCEL',$this,$user,$langs,$conf);
				if ($result < 0) {
					$error++; $this->errors=$interface->errors;
				}
				// Fin appel triggers
			}

            if (! $error)
			{
				$this->statut=-1;
            	$this->on_process=0;
            	$this->db->commit();
            	return 1;
        	}
			else
			{
				$this->error=$mouvP->error;
				$this->db->rollback();
				return -1;
			}
		}
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	Create repair
     *	Note that this->ref can be set or empty. If empty, we will use "(PROV)"
     *
     *	@param		User	$user 		Objet user that make creation
     *	@param		int		$notrigger	Disable all triggers
     *	@return 	int					<0 if KO, >0 if OK
     */
    function create($user, $notrigger=0)
    {
        global $conf,$langs,$mysoc;
        $error=0;

        // Clean parameters
        $this->brouillon = 1;		// On positionne en mode brouillon la reparation

        dol_syslog(get_class($this)."::create user=".$user->id);

        // Check parameters
        $soc = new Societe($this->db);
        $result=$soc->fetch($this->socid);
        if ($result < 0)
        {
            $this->error="Failed to fetch company";
            dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
            return -2;
        }
        if (! empty($conf->global->REPAIR_REQUIRE_SOURCE) && $this->source < 0)
        {
            $this->error=$langs->trans("ErrorFieldRequired",$langs->trans("Source"));
            dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
            return -1;
        }
//<Tathar>
		$machine_obj = new Machine($this->db);
		$machine_obj->trademark = $this->trademark;
		$machine_obj->model = $this->model;
		$machine_obj->type_id = $this->type_id;
		$machine_obj->n_model = $this->n_model;
		$machine_obj->serial_num = $this->serial_num;
		$result = $machine_obj->create($user, $notrigger);
		dol_syslog(get_class($this)."::create Machine", LOG_DEBUG);
		if ($result < 0)
		{
            dol_syslog(get_class($this)."::create machine_obj->create return ".$result, LOG_ERR);
            $this->db->rollback();
            return -1;
		}
		$fk_machine = $result;
//</Tathar>
        // $date_repair is deprecated
        $date = ($this->date_repair ? $this->date_repair : $this->date);

        $now=dol_now();

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."repair (";
        $sql.= " ref, fk_soc, date_creation, fk_user_author, fk_projet, fk_machine, breakdown, support_id, accessory, date_repair, source, note, note_public, ref_client, ref_int";
        $sql.= ", model_pdf, fk_cond_reglement, fk_mode_reglement, fk_availability, fk_input_reason, date_livraison, fk_adresse_livraison";
        $sql.= ", remise_absolue, remise_percent";
        $sql.= ", entity";
        $sql.= ")";
        $sql.= " VALUES ('(PROV)',".$this->socid.", ".$this->db->idate($now).", ".$user->id;
        $sql.= ", ".($this->fk_project?$this->fk_project:"null");
        $sql.= ", ".$fk_machine;
		$sql.= ", '".$this->breakdown."'";
		$sql.= ", '".$this->support_id."'";
		$sql.= ", '".$this->accessory."'";
        $sql.= ", ".$this->db->idate($date);
        $sql.= ", ".($this->source>=0 && $this->source != '' ?$this->source:'null');
        $sql.= ", '".$this->db->escape($this->note)."'";
        $sql.= ", '".$this->db->escape($this->note_public)."'";
        $sql.= ", '".$this->db->escape($this->ref_client)."'";
        $sql.= ", ".($this->ref_int?"'".$this->db->escape($this->ref_int)."'":"null");
        $sql.= ", '".$this->modelpdf."'";
        $sql.= ", ".($this->cond_reglement_id>0?"'".$this->cond_reglement_id."'":"null");
        $sql.= ", ".($this->mode_reglement_id>0?"'".$this->mode_reglement_id."'":"null");
        $sql.= ", ".($this->availability_id>0?"'".$this->availability_id."'":"null");
        $sql.= ", ".($this->demand_reason_id>0?"'".$this->demand_reason_id."'":"null");
        $sql.= ", ".($this->date_livraison?"'".$this->db->idate($this->date_livraison)."'":"null");
        $sql.= ", ".($this->fk_delivery_address>0?$this->fk_delivery_address:'NULL');
        $sql.= ", ".($this->remise_absolue>0?$this->remise_absolue:'NULL');
        $sql.= ", '".$this->remise_percent."'";
        $sql.= ", ".$conf->entity;
        $sql.= ")";

        dol_syslog("Repair::create sql=".$sql);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'repair');

            if ($this->id)
            {
                $fk_parent_line=0;
                $num=count($this->lines);

                /*
                 *  Insertion du detail des produits dans la base
                 */
                for ($i=0;$i<$num;$i++)
                {
                    // Reset fk_parent_line for no child products and special product
                    if (($this->lines[$i]->product_type != 9 && empty($this->lines[$i]->fk_parent_line)) || $this->lines[$i]->product_type == 9) {
                        $fk_parent_line = 0;
                    }

                    $result = $this->addline(
                        $this->id,
                        $this->lines[$i]->desc,
                        $this->lines[$i]->subprice,
                        $this->lines[$i]->qty,
                        $this->lines[$i]->tva_tx,
                        $this->lines[$i]->localtax1_tx,
                        $this->lines[$i]->localtax2_tx,
                        $this->lines[$i]->fk_product,
                        $this->lines[$i]->remise_percent,
                        $this->lines[$i]->info_bits,
                        $this->lines[$i]->fk_remise_except,
                        'HT',
                        0,
                        $this->lines[$i]->date_start,
                        $this->lines[$i]->date_end,
                        $this->lines[$i]->product_type,
                        $this->lines[$i]->rang,
                        $this->lines[$i]->special_code,
                        $fk_parent_line,
                        $this->lines[$i]->fk_fournprice,
                        $this->lines[$i]->pa_ht,
                    	$this->lines[$i]->label
                    );
                    if ($result < 0)
                    {
                        $this->error=$this->db->lasterror();
                        dol_print_error($this->db);
                        $this->db->rollback();
                        return -1;
                    }
                    // Defined the new fk_parent_line
                    if ($result > 0 && $this->lines[$i]->product_type == 9) {
                        $fk_parent_line = $result;
                    }
                }

                // Mise a jour ref
//<Tathar>
				$this->date = $date;
//</Tathar>
				$sql = 'UPDATE '.MAIN_DB_PREFIX."repair SET ref='(PROV".$this->id.")' WHERE rowid=".$this->id;
                if ($this->db->query($sql))
                {
                    if ($this->id)
                    {
                        $this->ref="(PROV".$this->id.")";

                        // Add object linked
                        if (is_array($this->linked_objects) && ! empty($this->linked_objects))
                        {
                        	foreach($this->linked_objects as $origin => $origin_id)
                        	{
                        		$ret = $this->add_object_linked($origin, $origin_id);
                        		if (! $ret)
                        		{
                        			dol_print_error($this->db);
                        			$error++;
                        		}

                        		// TODO mutualiser
                        		if ($origin == 'propal' && $origin_id)
                        		{
                        			// On recupere les differents contact interne et externe
                        			$prop = new Propal($this->db, $this->socid, $origin_id);

                        			// On recupere le commercial suivi propale
                        			$this->userid = $prop->getIdcontact('internal', 'SALESREPFOLL');

                        			if ($this->userid)
                        			{
                        				//On passe le commercial suivi propale en commercial suivi repair
                        				$this->add_contact($this->userid[0], 'SALESREPFOLL', 'internal');
                        			}

                        			// On recupere le contact client suivi propale
                        			$this->contactid = $prop->getIdcontact('external', 'CUSTOMER');

                        			if ($this->contactid)
                        			{
                        				//On passe le contact client suivi propale en contact client suivi repair
                        				$this->add_contact($this->contactid[0], 'CUSTOMER', 'external');
                        			}
                        		}
                        	}
                        }
                    }

                    if (! $notrigger)
                    {
                        // Appel des triggers
                        include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
                        $interface=new Interfaces($this->db);
                        $result=$interface->run_triggers('REPAIR_CREATE',$this,$user,$langs,$conf);
                        if ($result < 0) { $error++; $this->errors=$interface->errors; }
                        // Fin appel triggers
                    }

                    $this->db->commit();
                    return $this->id;
                }
                else
                {
                    $this->db->rollback();
                    return -1;
                }
            }
        }
        else
        {
            dol_print_error($this->db);
            $this->db->rollback();
            return -1;
        }
    }


    /**
     *	Load an object from its id and create a new one in database
     *
     *	@param		int			$socid			Id of thirdparty
     *	@param		HookManager	$hookmanager	Hook manager instance
     *	@return		int							New id of clone
     */
    function createFromClone($socid=0,$hookmanager=false)
    {
        global $conf,$user,$langs;

        $error=0;

        $this->db->begin();

        // Load source object
        $objFrom = dol_clone($this);

        // Change socid if needed
        if (! empty($socid) && $socid != $this->socid)
        {
            $objsoc = new Societe($this->db);

            if ($objsoc->fetch($socid)>0)
            {
                $this->socid 				= $objsoc->id;
                $this->cond_reglement_id	= (! empty($objsoc->cond_reglement_id) ? $objsoc->cond_reglement_id : 0);
                $this->mode_reglement_id	= (! empty($objsoc->mode_reglement_id) ? $objsoc->mode_reglement_id : 0);
                $this->fk_project			= '';
                $this->fk_delivery_address	= '';
            }

            // TODO Change product price if multi-prices
        }

        $this->id=0;
        $this->statut=0;
		$this->on_progress=0;

        // Clear fields
        $this->user_author_id     = $user->id;
        $this->user_valid         = '';
        $this->date_creation      = '';
        $this->date_validation    = '';
        $this->ref_client         = '';

        // Create clone
        $result=$this->create($user);
        if ($result < 0) $error++;

        if (! $error)
        {
            // Hook of thirdparty module
            if (is_object($hookmanager))
            {
                $parameters=array('objFrom'=>$objFrom);
                $action='';
                $reshook=$hookmanager->executeHooks('createFrom',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks
                if ($reshook < 0) $error++;
            }

            // Appel des triggers
            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('ORDER_CLONE',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }

        // End
        if (! $error)
        {
            $this->db->commit();
            return $this->id;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }
    }


    /**
     *  Load an object from a proposal and create a new order into database
     *
     *  @param      Object			$object 	        Object source
     *  @return     int             					<0 if KO, 0 if nothing done, 1 if OK
     */
    function createFromProposal($object)
    {
        global $conf,$user,$langs;
        global $hookmanager;

        $error=0;

        // Signed proposal
        if ($object->statut == 2)
        {
            $this->date_repair = dol_now();
            $this->source = 0;

            $num=count($object->lines);
            for ($i = 0; $i < $num; $i++)
            {
                $line = new RepairLine($this->db);

                $line->libelle           = $object->lines[$i]->libelle;
                $line->label             = $object->lines[$i]->label;
                $line->desc              = $object->lines[$i]->desc;
                $line->price             = $object->lines[$i]->price;
                $line->subprice          = $object->lines[$i]->subprice;
                $line->tva_tx            = $object->lines[$i]->tva_tx;
                $line->localtax1_tx      = $object->lines[$i]->localtax1_tx;
                $line->localtax2_tx      = $object->lines[$i]->localtax2_tx;
                $line->qty               = $object->lines[$i]->qty;
                $line->fk_remise_except  = $object->lines[$i]->fk_remise_except;
                $line->remise_percent    = $object->lines[$i]->remise_percent;
                $line->fk_product        = $object->lines[$i]->fk_product;
                $line->info_bits         = $object->lines[$i]->info_bits;
                $line->product_type      = $object->lines[$i]->product_type;
                $line->rang              = $object->lines[$i]->rang;
                $line->special_code      = $object->lines[$i]->special_code;
                $line->fk_parent_line    = $object->lines[$i]->fk_parent_line;

                $this->lines[$i] = $line;
            }

            $this->socid                = $object->socid;
            $this->fk_project           = $object->fk_project;
            $this->cond_reglement_id    = $object->cond_reglement_id;
            $this->mode_reglement_id    = $object->mode_reglement_id;
            $this->availability_id      = $object->availability_id;
            $this->demand_reason_id     = $object->demand_reason_id;
            $this->date_livraison       = $object->date_livraison;
            $this->fk_delivery_address  = $object->fk_delivery_address;
            $this->contact_id           = $object->contactid;
            $this->ref_client           = $object->ref_client;
            $this->note                 = $object->note;
            $this->note_public          = $object->note_public;

            $this->origin				= $object->element;
            $this->origin_id			= $object->id;

            // Possibility to add external linked objects with hooks
            $this->linked_objects[$this->origin] = $this->origin_id;
            if (is_array($object->other_linked_objects) && ! empty($object->other_linked_objects))
            {
            	$this->linked_objects = array_merge($this->linked_objects, $object->other_linked_objects);
            }

            $ret = $this->create($user);

            if ($ret > 0)
            {
                // Actions hooked (by external module)
                if (! is_object($hookmanager))
                {
                	include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
                	$hookmanager=new HookManager($this->db);
                }
                $hookmanager->initHooks(array('orderdao'));

                $parameters=array('objFrom'=>$object);
                $action='';
                $reshook=$hookmanager->executeHooks('createFrom',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks
                if ($reshook < 0) $error++;

                if (! $error)
                {
                    // Ne pas passer par la repair provisoire
                    if ($conf->global->REPAIR_VALID_AFTER_CLOSE_PROPAL == 1)
                    {
                        $this->fetch($ret);
                        $this->valid($user);
                    }
                    return 1;
                }
                else return -1;
            }
            else return -1;
        }
        else return 0;
    }

























































    /**
     *	Make Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function makeRepair($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != 4)
        {
			dol_syslog(get_class($this)."::makeRepair wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->MakeRepair)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 0,";
        $sql.= " repair_statut = 5";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::makeRepair sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=0;
            $this->repair_statut=5;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	UnAccept Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function unaccepteEstimate($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != 4)
        {
			dol_syslog(get_class($this)."::unaccepteEstimate wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->ValidateReplies)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 1,";
        $sql.= " repair_statut = 3";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::unaccepteEstimate sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=1;
            $this->repair_statut=3;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	Finish Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function finishRepair($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != 5)
        {
			dol_syslog(get_class($this)."::finishRepair wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->MakeRepair)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 1,";
        $sql.= " repair_statut = 6";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::finishRepair sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=1;
            $this->repair_statut=6;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	Modify Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function modifyRepair($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != 6)
        {
			dol_syslog(get_class($this)."::modifyRepair wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->MakeRepair)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 0,";
        $sql.= " repair_statut = 5";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::modifyRepair sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=0;
            $this->repair_statut=5;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }
    /**
     *	Unvalide Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function unvalideRepair($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != 7)
        {
			dol_syslog(get_class($this)."::unvalideRepair wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->ValidateRepair)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 1,";
        $sql.= " repair_statut = 6";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::unvalideRepair sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=1;
            $this->repair_statut=6;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *	Reopen Canceled Repair
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function reopen_canceledRepair($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != -1)
        {
			dol_syslog(get_class($this)."::reopen_canceledRepair wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->annuler)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 0,";
        $sql.= " repair_statut = 0";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::reopen_canceledRepair sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=0;
            $this->repair_statut=5;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	Reopen Unvalided Estimate
     *
     *	@param	User	$user			Object user that modify
     *	@param	int		$idwarehouse	Id warehouse to use for stock change.
     *	@return	int						<0 if KO, >0 if OK
     */
    function reopen_unvalidedEstimate($user, $idwarehouse=-1)
    {
        global $conf,$langs;

        $error=0;

        // Protection
        if ($this->repair_statut != -2)
        {
			dol_syslog(get_class($this)."::reopen_unvalidedEstimate wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->ValidateReplies)
        {
            $this->error='Permission denied';
            return -1;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET";
		$sql.= " fk_statut = 0,";
        $sql.= " repair_statut = 0";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::reopen_unvalidedEstimate sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            $this->statut=0;
            $this->repair_statut=0;
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }



    /**
     *	Validate order
     *
     *	@param		User	$user     		User making status change
     *	@param		int		$idwarehouse	Id of warehouse to use for stock decrease
     *	@return  	int						<=0 if OK, >0 if KO
     */
/*    function valid($user, $idwarehouse=0)
    {
        global $conf,$langs;
        require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

        $error=0;

        // Protection
        if ($this->statut == 1)
        {
            dol_syslog(get_class($this)."::valid no draft status", LOG_WARNING);
            return 0;
        }

        if (! $user->rights->repair->valider)
        {
            $this->error='Permission denied';
            dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
            return -1;
        }

        $now=dol_now();

        $this->db->begin();

        // Definition du nom de module de numerotation de repair
        $soc = new Societe($this->db);
        $soc->fetch($this->socid);

        // Class of company linked to order
        $result=$soc->set_as_client();

        // Define new ref
        if (! $error && (preg_match('/^[\(]?PROV/i', $this->ref)))
        {
            $num = $this->getNextNumRef($soc);
        }
        else
        {
            $num = $this->ref;
        }

        // Validate
        $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
        $sql.= " SET ref = '".$num."',";
        $sql.= " fk_statut = 1,";
        $sql.= " date_valid='".$this->db->idate($now)."',";
        $sql.= " fk_user_valid = ".$user->id;
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog(get_class($this)."::valid() sql=".$sql);
        $resql=$this->db->query($sql);
        if (! $resql)
        {
            dol_syslog(get_class($this)."::valid Echec update - 10 - sql=".$sql, LOG_ERR);
            dol_print_error($this->db);
            $error++;
        }

        if (! $error)
        {
            // If stock is incremented on validate order, we must increment it
            if ($result >= 0 && $conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
            {
                require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");
                $langs->load("agenda");

                // Loop on each line
                $cpt=count($this->lines);
                for ($i = 0; $i < $cpt; $i++)
                {
                    if ($this->lines[$i]->fk_product > 0)
                    {
                        $mouvP = new MouvementStock($this->db);
                        // We decrement stock of product (and sub-products)
                        $result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("RepairValidatedInDolibarr",$num));
                        if ($result < 0) { $error++; }
                    }
                }
            }
        }

        if (! $error)
        {
            $this->oldref='';

            // Rename directory if dir was a temporary ref
            if (preg_match('/^[\(]?PROV/i', $this->ref))
            {
                // On renomme repertoire ($this->ref = ancienne ref, $numfa = nouvelle ref)
                // afin de ne pas perdre les fichiers attaches
                $comref = dol_sanitizeFileName($this->ref);
                $snum = dol_sanitizeFileName($num);
                $dirsource = $conf->repair->dir_output.'/'.$comref;
                $dirdest = $conf->repair->dir_output.'/'.$snum;
                if (file_exists($dirsource))
                {
                    dol_syslog(get_class($this)."::valid() rename dir ".$dirsource." into ".$dirdest);

                    if (@rename($dirsource, $dirdest))
                    {
                        $this->oldref = $comref;

                        dol_syslog("Rename ok");
                        // Suppression ancien fichier PDF dans nouveau rep
                        dol_delete_file($conf->repair->dir_output.'/'.$snum.'/'.$comref.'*.*');
                    }
                }
            }
        }

        // Set new ref and current status
        if (! $error)
        {
            $this->ref = $num;
            $this->statut = 1;
        }

        if (! $error)
        {
            // Appel des triggers
            include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('ORDER_VALIDATE',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }

        if (! $error)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            $this->error=$this->db->lasterror();
            return -1;
        }
    }
*/




    /**
     *	Delete Repair
     *
     *	@param	User	$user		User object
     *	@param	int		$notrigger	1=Does not execute triggers, 0= execuete triggers
     * 	@return	int					<=0 if KO, >0 if OK
     */
    function delete($user, $notrigger=0)
    {
        global $conf, $langs;
        require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");

        $error = 0;
		
		// Protection
        if (($this->repair_statut >= 3 ) || ($this->repair_statut < 0 ))
        {
			dol_syslog(get_class($this)."::delete wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        $this->db->begin();

        if (! $error && ! $notrigger)
        {
        	// Appel des triggers
        	include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
        	$interface=new Interfaces($this->db);
        	$result=$interface->run_triggers('DELETE_REPAIR',$this,$user,$langs,$conf);
        	if ($result < 0) {
        		$error++; $this->errors=$interface->errors;
        	}
        	// Fin appel triggers
        }

        if (! $error)
        {
        	// Delete order details
        	$sql = 'DELETE FROM '.MAIN_DB_PREFIX."repairdet WHERE fk_repair = ".$this->id;
        	dol_syslog("Repair::delete sql=".$sql);
        	if (! $this->db->query($sql) )
        	{
        		dol_syslog(get_class($this)."::delete error", LOG_ERR);
        		$error++;
        	}

        	// Delete order
        	$sql = 'DELETE FROM '.MAIN_DB_PREFIX."repair WHERE rowid = ".$this->id;
        	dol_syslog(get_class($this)."::delete sql=".$sql, LOG_DEBUG);
        	if (! $this->db->query($sql) )
        	{
        		dol_syslog(get_class($this)."::delete error", LOG_ERR);
        		$error++;
        	}

        	// Delete linked object
        	$res = $this->deleteObjectLinked();
        	if ($res < 0) $error++;

        	// Delete linked contacts
        	$res = $this->delete_linked_contact();
        	if ($res < 0) $error++;

        	// On efface le repertoire de pdf
        	$comref = dol_sanitizeFileName($this->ref);
        	if ($conf->repair->dir_output)
        	{
        		$dir = $conf->repair->dir_output . "/" . $comref ;
        		$file = $conf->repair->dir_output . "/" . $comref . "/" . $comref . ".pdf";
        		if (file_exists($file))	// We must delete all files before deleting directory
        		{
        			dol_delete_preview($this);

        			if (! dol_delete_file($file,0,0,0,$this)) // For triggers
        			{
        				$this->error=$langs->trans("ErrorCanNotDeleteFile",$file);
        				$this->db->rollback();
        				return 0;
        			}
        		}
        		if (file_exists($dir))
        		{
        			if (! dol_delete_dir_recursive($dir))
        			{
        				$this->error=$langs->trans("ErrorCanNotDeleteDir",$dir);
        				$this->db->rollback();
        				return 0;
        			}
        		}
        	}
        }

        if (! $error)
        {
			$objmac = new Machine($this->db);
			$objmac->clean($user, $notrigger);
        	dol_syslog(get_class($this)."::delete $this->id by $user->id", LOG_DEBUG);
        	$this->db->commit();
        	return 1;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_syslog(get_class($this)."::delete ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }



    /**
     *	Add an order line into database (linked to product/service or not)
     *
     *	@param      int				$repairid      	Id of line
     *	@param      string			$desc            	Description of line
     *	@param      double			$pu_ht    	        Unit price (without tax)
     *	@param      double			$qty             	Quantite
     *	@param      double			$txtva           	Taux de tva force, sinon -1
     *	@param      double			$txlocaltax1		Local tax 1 rate
     *	@param      double			$txlocaltax2		Local tax 2 rate
     *	@param      int				$fk_product      	Id du produit/service predefini
     *	@param      double			$remise_percent  	Pourcentage de remise de la ligne
     *	@param      int				$info_bits			Bits de type de lignes
     *	@param      int				$fk_remise_except	Id remise
     *	@param      string			$price_base_type	HT or TTC
     *	@param      double			$pu_ttc    		    Prix unitaire TTC
     *	@param      timestamp		$date_start       	Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
     *	@param      timestamp		$date_end         	End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
     *	@param      int				$type				Type of line (0=product, 1=service)
     *	@param      int				$rang             	Position of line
     *	@param		int				$special_code		Special code
     *	@param		int				$fk_parent_line		Parent line
     *	@return     int             					>0 if OK, <0 if KO
     *
     *	@see        add_product
     *
     *	Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
     *	de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
     *	par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,produit)
     *	et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
     */
    function addline($repairid, $desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $remise_percent=0, $info_bits=0, $fk_remise_except=0, $price_base_type='HT', $pu_ttc=0, $date_start='', $date_end='', $type=0, $rang=-1, $special_code=0, $fk_parent_line=0)
    {
        dol_syslog("Repair::addline repairid=$repairid, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_percent=$remise_percent, info_bits=$info_bits, fk_remise_except=$fk_remise_except, price_base_type=$price_base_type, pu_ttc=$pu_ttc, date_start=$date_start, date_end=$date_end, type=$type", LOG_DEBUG);

        include_once(DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php');

        // Clean parameters
        if (empty($remise_percent)) $remise_percent=0;
        if (empty($qty)) $qty=0;
        if (empty($info_bits)) $info_bits=0;
        if (empty($rang)) $rang=0;
        if (empty($txtva)) $txtva=0;
        if (empty($txlocaltax1)) $txlocaltax1=0;
        if (empty($txlocaltax2)) $txlocaltax2=0;
        if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line=0;

        $remise_percent=price2num($remise_percent);
        $qty=price2num($qty);
        $pu_ht=price2num($pu_ht);
        $pu_ttc=price2num($pu_ttc);
        $txtva = price2num($txtva);
        $txlocaltax1 = price2num($txlocaltax1);
        $txlocaltax2 = price2num($txlocaltax2);
        if ($price_base_type=='HT')
        {
            $pu=$pu_ht;
        }
        else
        {
            $pu=$pu_ttc;
        }
        $desc=trim($desc);

        // Check parameters
        if ($type < 0) return -1;

        if ($this->statut == 0)
        {
            $this->db->begin();

            // Calcul du total TTC et de la TVA pour la ligne a partir de
            // qty, pu, remise_percent et txtva
            // TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
            // la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
            $tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
            $total_ht  = $tabprice[0];
            $total_tva = $tabprice[1];
            $total_ttc = $tabprice[2];
            $total_localtax1 = $tabprice[9];
            $total_localtax2 = $tabprice[10];

            // Rang to use
            $rangtouse = $rang;
            if ($rangtouse == -1)
            {
                $rangmax = $this->line_max($fk_parent_line);
                $rangtouse = $rangmax + 1;
            }

            // TODO A virer
            // Anciens indicateurs: $price, $remise (a ne plus utiliser)
            $price = $pu;
            $remise = 0;
            if ($remise_percent > 0)
            {
                $remise = round(($pu * $remise_percent / 100), 2);
                $price = $pu - $remise;
            }

            // Insert line
            $this->line=new RepairLine($this->db);

            $this->line->fk_repair=$repairid;
            $this->line->desc=$desc;
            $this->line->qty=$qty;
            $this->line->tva_tx=$txtva;
            $this->line->localtax1_tx=$txlocaltax1;
            $this->line->localtax2_tx=$txlocaltax2;
            $this->line->fk_product=$fk_product;
            $this->line->fk_remise_except=$fk_remise_except;
            $this->line->remise_percent=$remise_percent;
            $this->line->subprice=$pu_ht;
            $this->line->rang=$rangtouse;
            $this->line->info_bits=$info_bits;
            $this->line->total_ht=$total_ht;
            $this->line->total_tva=$total_tva;
            $this->line->total_localtax1=$total_localtax1;
            $this->line->total_localtax2=$total_localtax2;
            $this->line->total_ttc=$total_ttc;
            $this->line->product_type=$type;
            $this->line->special_code=$special_code;
            $this->line->fk_parent_line=$fk_parent_line;

            $this->line->date_start=$date_start;
            $this->line->date_end=$date_end;

            // TODO Ne plus utiliser
            $this->line->price=$price;
            $this->line->remise=$remise;

            $result=$this->line->insert();
            if ($result > 0)
            {
                // Reorder if child line
                if (! empty($fk_parent_line)) $this->line_order(true,'DESC');

                // Mise a jour informations denormalisees au niveau de la repair meme
                $this->id=$repairid;	// TODO A virer
                $result=$this->update_price(1);
                if ($result > 0)
                {
                    $this->db->commit();
                    return $this->line->rowid;
                }
                else
                {
                    $this->db->rollback();
                    return -1;
                }
            }
            else
            {
                $this->error=$this->line->error;
                dol_syslog("Repair::addline error=".$this->error, LOG_ERR);
                $this->db->rollback();
                return -2;
            }
        }
    }


    /**
     *	Add line into array
     *	$this->client must be loaded
     *
     *	@param		int				$idproduct			Product Id
     *	@param		double			$qty				Quantity
     *	@param		double			$remise_percent		Product discount relative
     * 	@param    	timestamp		$date_start         Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
     * 	@param    	timestamp		$date_end           End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
     * 	@return    	void
     *
     *	TODO	Remplacer les appels a cette fonction par generation objet Ligne
     *			insere dans tableau $this->products
     */
    function add_product($idproduct, $qty, $remise_percent=0, $date_start='', $date_end='')
    {
        global $conf, $mysoc;

        if (! $qty) $qty = 1;

        if ($idproduct > 0)
        {
            $prod=new Product($this->db);
            $prod->fetch($idproduct);

            $tva_tx = get_default_tva($mysoc,$this->client,$prod->id);
            $localtax1_tx=get_localtax($tva_tx,1,$this->client);
            $localtax2_tx=get_localtax($tva_tx,2,$this->client);
            // multiprix
            if($conf->global->PRODUIT_MULTIPRICES && $this->client->price_level)
            $price = $prod->multiprices[$this->client->price_level];
            else
            $price = $prod->price;

            $line=new RepairLine($this->db);

            $line->fk_product=$idproduct;
            $line->desc=$prod->description;
            $line->qty=$qty;
            $line->subprice=$price;
            $line->remise_percent=$remise_percent;
            $line->tva_tx=$tva_tx;
            $line->localtax1_tx=$localtax1_tx;
            $line->localtax2_tx=$localtax2_tx;
            $line->ref=$prod->ref;
            $line->libelle=$prod->libelle;
            $line->product_desc=$prod->description;

            // Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
            // Save the start and end date of the line in the object
            if ($date_start) { $line->date_start = $date_start; }
            if ($date_end)   { $line->date_end = $date_end; }

            $this->lines[] = $line;

            /** POUR AJOUTER AUTOMATIQUEMENT LES SOUSPRODUITS a LA REPAIR
             if (! empty($conf->global->PRODUIT_SOUSPRODUITS))
             {
             $prod = new Product($this->db);
             $prod->fetch($idproduct);
             $prod -> get_sousproduits_arbo ();
             $prods_arbo = $prod->get_each_prod();
             if(count($prods_arbo) > 0)
             {
             foreach($prods_arbo as $key => $value)
             {
             // print "id : ".$value[1].' :qty: '.$value[0].'<br>';
             if(! in_array($value[1],$this->products))
             $this->add_product($value[1], $value[0]);

             }
             }

             }
             **/
        }
    }


    /**
     *	Get object and lines from database
     *
     *	@param      int			$id       		Id of object to load
     * 	@param		string		$ref			Ref of object
     * 	@param		string		$ref_ext		External reference of object
     * 	@param		string		$ref_int		Internal reference of other object
     *	@return     int         				>0 if OK, <0 if KO, 0 if not found
     */
    function fetch($id, $ref='', $ref_ext='', $ref_int='')
    {
        global $conf;

        // Check parameters
        if (empty($id) && empty($ref) && empty($ref_ext) && empty($ref_int)) return -1;

        $sql = 'SELECT c.rowid, c.date_creation, c.ref, c.fk_soc, c.fk_user_author, c.fk_statut, c.repair_statut';
        $sql.= ', c.amount_ht, c.total_ht, c.total_ttc, c.tva as total_tva, c.localtax1 as total_localtax1, c.localtax2 as total_localtax2, c.fk_cond_reglement, c.fk_mode_reglement, c.fk_availability, c.fk_input_reason';
        $sql.= ', c.date_repair';
        $sql.= ', c.date_livraison';
		$sql.= ', c.fk_machine, c.breakdown, c.support_id, c.accessory';
        $sql.= ', c.fk_projet, c.remise_percent, c.remise, c.remise_absolue, c.source, c.facture as facturee';
        $sql.= ', c.note as note_private, c.note_public, c.ref_client, c.ref_ext, c.ref_int, c.model_pdf, c.fk_adresse_livraison, c.extraparams';
        $sql.= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
        $sql.= ', cr.code as cond_reglement_code, cr.libelle as cond_reglement_libelle, cr.libelle_facture as cond_reglement_libelle_doc';
        $sql.= ', ca.code as availability_code';
        $sql.= ', dr.code as demand_reason_code';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'repair as c';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_payment_term as cr ON (c.fk_cond_reglement = cr.rowid)';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as p ON (c.fk_mode_reglement = p.id)';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_availability as ca ON (c.fk_availability = ca.rowid)';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_input_reason as dr ON (c.fk_input_reason = ca.rowid)';
        $sql.= " WHERE c.entity = ".$conf->entity;
        if ($id)   	  $sql.= " AND c.rowid=".$id;
        if ($ref)     $sql.= " AND c.ref='".$this->db->escape($ref)."'";
        if ($ref_ext) $sql.= " AND c.ref_ext='".$this->db->escape($ref_ext)."'";
        if ($ref_int) $sql.= " AND c.ref_int='".$this->db->escape($ref_int)."'";

        dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result)
        {
            $obj = $this->db->fetch_object($result);
            if ($obj)
            {
                $this->id                     = $obj->rowid;
                $this->ref                    = $obj->ref;
                $this->ref_client             = $obj->ref_client;
                $this->ref_ext				  = $obj->ref_ext;
                $this->ref_int				  = $obj->ref_int;
                $this->socid                  = $obj->fk_soc;
                $this->statut                 = $obj->fk_statut;
                $this->repair_statut          = $obj->repair_statut;
                $this->user_author_id         = $obj->fk_user_author;
                $this->total_ht               = $obj->total_ht;
                $this->total_tva              = $obj->total_tva;
                $this->total_localtax1		  = $obj->total_localtax1;
                $this->total_localtax2		  = $obj->total_localtax2;
                $this->total_ttc              = $obj->total_ttc;
                $this->date                   = $this->db->jdate($obj->date_repair);
                $this->date_repair            = $this->db->jdate($obj->date_repair);
                $this->remise                 = $obj->remise;
                $this->remise_percent         = $obj->remise_percent;
                $this->remise_absolue         = $obj->remise_absolue;
                $this->source                 = $obj->source;
                $this->facturee               = $obj->facturee;
                $this->note                   = $obj->note_private;	// deprecated
                $this->note_private           = $obj->note_private;
                $this->note_public            = $obj->note_public;
                $this->breakdown              = $obj->breakdown;
                $this->support_id             = $obj->support_id;
                $this->accessory              = $obj->accessory;
                $this->fk_project             = $obj->fk_projet;
                $this->modelpdf               = $obj->model_pdf;
                $this->mode_reglement_id      = $obj->fk_mode_reglement;
                $this->mode_reglement_code    = $obj->mode_reglement_code;
                $this->mode_reglement         = $obj->mode_reglement_libelle;
                $this->cond_reglement_id      = $obj->fk_cond_reglement;
                $this->cond_reglement_code    = $obj->cond_reglement_code;
                $this->cond_reglement         = $obj->cond_reglement_libelle;
                $this->cond_reglement_doc     = $obj->cond_reglement_libelle_doc;
                $this->availability_id		  = $obj->fk_availability;
                $this->availability_code      = $obj->availability_code;
                $this->demand_reason_id		  = $obj->fk_input_reason;
                $this->demand_reason_code     = $obj->demand_reason_code;
                $this->date_livraison         = $this->db->jdate($obj->date_livraison);
                $this->fk_delivery_address    = $obj->fk_adresse_livraison;

                $this->extraparams			  = (array) json_decode($obj->extraparams, true);


                $fk_machine             = $obj->fk_machine;

                $this->lines                 = array();

                if ($this->statut == 0 )
				{
					$this->brouillon = 1;
				}


                $this->db->free();

				/*
                 * trademark & model & N° model & N° Serie & type_id
                 */
				$objmac = new Machine($this->db);
				$result = $objmac->fetch($fk_machine);
                if ($result < 0)
                {
                    return -4;
                }
				if ($result)
                {
                    $this->trademark  = $objmac->trademark;
					$this->model      = $objmac->model;
					$this->type_id    = $objmac->type_id;
					$this->n_model    = $objmac->n_model;					
                	$this->serial_num = $objmac->serial_num;
                }

                /*
                 * Lines
                 */
                $result=$this->fetch_lines();
                if ($result < 0)
                {
                    return -3;
                }
                return 1;
            }
            else
            {
                $this->error='Order with id '.$id.' not found sql='.$sql;
                dol_syslog(get_class($this).'::fetch '.$this->error);
                return 0;
            }
        }
        else
        {
            dol_syslog(get_class($this).'::fetch Error rowid='.$id, LOG_ERR);
            $this->error=$this->db->error();
            return -1;
        }
    }


    /**
     *	Adding line of fixed discount in the order in DB
     *
     *	@param     int	$idremise			Id de la remise fixe
     *	@return    int          			>0 si ok, <0 si ko
     */
    function insert_discount($idremise)
    {
        global $langs;

        include_once(DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php');
        include_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

        $this->db->begin();

        $remise=new DiscountAbsolute($this->db);
        $result=$remise->fetch($idremise);

        if ($result > 0)
        {
            if ($remise->fk_facture)	// Protection against multiple submission
            {
                $this->error=$langs->trans("ErrorDiscountAlreadyUsed");
                $this->db->rollback();
                return -5;
            }

            $line = new RepairLine($this->db);

            $line->fk_repair=$this->id;
            $line->fk_remise_except=$remise->id;
            $line->desc=$remise->description;   	// Description ligne
            $line->tva_tx=$remise->tva_tx;
            $line->subprice=-$remise->amount_ht;
            $line->price=-$remise->amount_ht;
            $line->fk_product=0;					// Id produit predefini
            $line->qty=1;
            $line->remise=0;
            $line->remise_percent=0;
            $line->rang=-1;
            $line->info_bits=2;

            $line->total_ht  = -$remise->amount_ht;
            $line->total_tva = -$remise->amount_tva;
            $line->total_ttc = -$remise->amount_ttc;

            $result=$line->insert();
            if ($result > 0)
            {
                $result=$this->update_price(1);
                if ($result > 0)
                {
                    $this->db->commit();
                    return 1;
                }
                else
                {
                    $this->db->rollback();
                    return -1;
                }
            }
            else
            {
                $this->error=$line->error;
                $this->db->rollback();
                return -2;
            }
        }
        else
        {
            $this->db->rollback();
            return -2;
        }
    }


    /**
     *	Load array lines
     *
     *	@param		int		$only_product	Return only physical products
     *	@return		int						<0 if KO, >0 if OK
     */
    function fetch_lines($only_product=0)
    {
        $this->lines=array();

        $sql = 'SELECT l.rowid, l.fk_product, l.fk_parent_line, l.product_type, l.fk_repair, l.label as custom_label, l.description, l.price, l.qty, l.tva_tx,';
        $sql.= ' l.localtax1_tx, l.localtax2_tx, l.fk_remise_except, l.remise_percent, l.subprice, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.rang, l.info_bits, l.special_code,';
        $sql.= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end,';
        $sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'repairdet as l';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
        $sql.= ' WHERE l.fk_repair = '.$this->id;
        if ($only_product) $sql .= ' AND p.fk_product_type = 0';
        $sql .= ' ORDER BY l.rang';

        dol_syslog("Repair::fetch_lines sql=".$sql,LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result)
        {
            $num = $this->db->num_rows($result);

            $i = 0;
            while ($i < $num)
            {
                $objp = $this->db->fetch_object($result);

                $line = new RepairLine($this->db);

                $line->rowid            = $objp->rowid;				// \deprecated
                $line->id               = $objp->rowid;
                $line->fk_repair        = $objp->fk_repair;
                $line->repair_id        = $objp->fk_repair;			// \deprecated
                $line->label            = $objp->custom_label;
                $line->desc             = $objp->description;  		// Description ligne
                $line->product_type     = $objp->product_type;
                $line->qty              = $objp->qty;
                $line->tva_tx           = $objp->tva_tx;
                $line->localtax1_tx     = $objp->localtax1_tx;
                $line->localtax2_tx     = $objp->localtax2_tx;
                $line->total_ht         = $objp->total_ht;
                $line->total_ttc        = $objp->total_ttc;
                $line->total_tva        = $objp->total_tva;
                $line->total_localtax1  = $objp->total_localtax1;
                $line->total_localtax2  = $objp->total_localtax2;
                $line->subprice         = $objp->subprice;
                $line->fk_remise_except = $objp->fk_remise_except;
                $line->remise_percent   = $objp->remise_percent;
                $line->price            = $objp->price;
                $line->fk_product       = $objp->fk_product;
				$line->fk_fournprice 	= $objp->fk_fournprice;
		      	$marginInfos			= getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $line->fk_fournprice, $objp->pa_ht);
		   		$line->pa_ht 			= $marginInfos[0];
		    	$line->marge_tx			= $marginInfos[1];
		     	$line->marque_tx		= $marginInfos[2];
                $line->rang             = $objp->rang;
                $line->info_bits        = $objp->info_bits;
                $line->special_code		= $objp->special_code;
                $line->fk_parent_line	= $objp->fk_parent_line;

                $line->ref				= $objp->product_ref;		// TODO deprecated
                $line->product_ref		= $objp->product_ref;
                $line->libelle			= $objp->product_label;		// TODO deprecated
                $line->product_label	= $objp->product_label;
                $line->product_desc     = $objp->product_desc; 		// Description produit
                $line->fk_product_type  = $objp->fk_product_type;	// Produit ou service

                $line->date_start       = $this->db->jdate($objp->date_start);
                $line->date_end         = $this->db->jdate($objp->date_end);

                $this->lines[$i] = $line;

                $i++;
            }
            $this->db->free($result);

            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog('Repair::fetch_lines: Error '.$this->error, LOG_ERR);
            return -3;
        }
    }


    /**
     *	Return number of line with type product.
     *
     *	@return		int		<0 if KO, Nbr of product lines if OK
     */
    function getNbOfProductsLines()
    {
        $nb=0;
        foreach($this->lines as $line)
        {
            if ($line->fk_product_type == 0) $nb++;
        }
        return $nb;
    }

    /**
     *	Load array this->expeditions of nb of products sent by line in order
     *
     *	@param      int		$filtre_statut      Filter on status
     * 	@return     int                			<0 if KO, Nb of lines found if OK
     *
     *	TODO deprecated, move to Shipping class
     */
    function loadExpeditions($filtre_statut=-1)
    {
        $num=0;
        $this->expeditions = array();

        $sql = 'SELECT cd.rowid, cd.fk_product,';
        $sql.= ' sum(ed.qty) as qty';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'expeditiondet as ed,';
        if ($filtre_statut >= 0) $sql.= ' '.MAIN_DB_PREFIX.'expedition as e,';
        $sql.= ' '.MAIN_DB_PREFIX.'repairdet as cd';
        $sql.= ' WHERE';
        if ($filtre_statut >= 0) $sql.= ' ed.fk_expedition = e.rowid AND';
        $sql.= ' ed.fk_origin_line = cd.rowid';
        $sql.= ' AND cd.fk_repair =' .$this->id;
        if ($filtre_statut >= 0) $sql.=' AND e.fk_statut = '.$filtre_statut;
        $sql.= ' GROUP BY cd.rowid, cd.fk_product';
        //print $sql;

        dol_syslog("Repair::loadExpeditions sql=".$sql,LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result)
        {
            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($result);
                $this->expeditions[$obj->rowid] = $obj->qty;
                $i++;
            }
            $this->db->free();
            return $num;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_syslog("Repair::loadExpeditions ".$this->error,LOG_ERR);
            return -1;
        }

    }

    /**
     * Returns a array with expeditions lines number
     *
     * @return	int		Nb of shipments
     *
     * TODO deprecated, move to Shipping class
     */
    function nb_expedition()
    {
        $sql = 'SELECT count(*)';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'expedition as e';
        $sql.= ', '.MAIN_DB_PREFIX.'element_element as el';
        $sql.= ' WHERE el.fk_source = '.$this->id;
        $sql.= " AND el.fk_target = e.rowid";
        $sql.= " AND el.targettype = 'shipping'";

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $row = $this->db->fetch_row($resql);
            return $row[0];
        }
        else dol_print_error($this->db);
    }

    /**
     *	Return a array with sendings by line
     *
     *	@param      int		$filtre_statut      Filtre sur statut
     *	@return     int                 		0 si OK, <0 si KO
     *
     *	TODO  deprecated, move to Shipping class
     */
    function livraison_array($filtre_statut=-1)
    {
        $delivery = new Livraison($this->db);
        $deliveryArray = $delivery->livraison_array($filtre_statut);
        return $deliveryArray;
    }

    /**
     *	Return a array with the pending stock by product
     *
     *	@param      int		$filtre_statut      Filtre sur statut
     *	@return     int                 		0 si OK, <0 si KO
     *
     *	TODO		FONCTION NON FINIE A FINIR
     */
    function stock_array($filtre_statut=-1)
    {
        $this->stocks = array();

        // Tableau des id de produit de la repair
		$array_of_product=array();

        // Recherche total en stock pour chaque produit
        // TODO $array_of_product est défini vide juste au dessus !!
        if (count($array_of_product))
        {
            $sql = "SELECT fk_product, sum(ps.reel) as total";
            $sql.= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
            $sql.= " WHERE ps.fk_product IN (".join(',',$array_of_product).")";
            $sql.= ' GROUP BY fk_product ';
            $result = $this->db->query($sql);
            if ($result)
            {
                $num = $this->db->num_rows($result);
                $i = 0;
                while ($i < $num)
                {
                    $obj = $this->db->fetch_object($result);
                    $this->stocks[$obj->fk_product] = $obj->total;
                    $i++;
                }
                $this->db->free();
            }
        }
        return 0;
    }

    /**
     *  Delete an order line
     *
     *  @param      int		$lineid		Id of line to delete
     *  @return     int        		 	>0 if OK, 0 if nothing to do, <0 if KO
     */
    function deleteline($lineid)
    {
        global $user;

        if ($this->statut == 0)
        {
            $this->db->begin();

            $sql = "SELECT fk_product, qty";
            $sql.= " FROM ".MAIN_DB_PREFIX."repairdet";
            $sql.= " WHERE rowid = ".$lineid;

            $result = $this->db->query($sql);
            if ($result)
            {
                $obj = $this->db->fetch_object($result);

                if ($obj)
                {
                    $product = new Product($this->db);
                    $product->id = $obj->fk_product;

                    // Delete line
                    $line = new RepairLine($this->db);

                    // For triggers
                    $line->fetch($lineid);

                    if ($line->delete() > 0)
                    {
                        $result=$this->update_price(1);

                        if ($result > 0)
                        {
                            $this->db->commit();
                            return 1;
                        }
                        else
                        {
                            $this->db->rollback();
                            $this->error=$this->db->lasterror();
                            return -1;
                        }
                    }
                    else
                    {
                        $this->db->rollback();
                        $this->error=$this->db->lasterror();
                        return -1;
                    }
                }
                else
                {
                    $this->db->rollback();
                    return 0;
                }
            }
            else
            {
                $this->db->rollback();
                $this->error=$this->db->lasterror();
                return -1;
            }
        }
        else
        {
            return -1;
        }
    }

    /**
     * 	Applique une remise relative
     *
     * 	@param     	User		$user		User qui positionne la remise
     * 	@param     	float		$remise		Discount (percent)
     *	@return		int 					<0 if KO, >0 if OK
     */
    function set_remise($user, $remise)
    {
        $remise=trim($remise)?trim($remise):0;

        if ($user->rights->repair->creer)
        {
            $remise=price2num($remise);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
            $sql.= ' SET remise_percent = '.$remise;
            $sql.= ' WHERE rowid = '.$this->id.' AND fk_statut = 0 ;';

            if ($this->db->query($sql))
            {
                $this->remise_percent = $remise;
                $this->update_price(1);
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                return -1;
            }
        }
    }


    /**
     * 		Applique une remise absolue
     *
     * 		@param     	User		$user 		User qui positionne la remise
     * 		@param     	float		$remise		Discount
     *		@return		int 					<0 if KO, >0 if OK
     */
    function set_remise_absolue($user, $remise)
    {
        $remise=trim($remise)?trim($remise):0;

        if ($user->rights->repair->creer)
        {
            $remise=price2num($remise);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
            $sql.= ' SET remise_absolue = '.$remise;
            $sql.= ' WHERE rowid = '.$this->id.' AND fk_statut = 0 ;';

            dol_syslog("Repair::set_remise_absolue sql=$sql");

            if ($this->db->query($sql))
            {
                $this->remise_absolue = $remise;
                $this->update_price(1);
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                return -1;
            }
        }
    }


    /**
     *	Set the order date
     *
     *	@param      User		$user       Object user making change
     *	@param      timestamp	$date		Date
     *	@return     int         			<0 if KO, >0 if OK
     */
    function set_date($user, $date)
    {
        if ($user->rights->repair->creer)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
            $sql.= " SET date_repair = ".($date ? $this->db->idate($date) : 'null');
            $sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

            dol_syslog("Repair::set_date sql=$sql",LOG_DEBUG);
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->date = $date;
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog("Repair::set_date ".$this->error,LOG_ERR);
                return -1;
            }
        }
        else
        {
            return -2;
        }
    }

    /**
     *	Set the planned delivery date
     *
     *	@param      User			$user        		Objet utilisateur qui modifie
     *	@param      timestamp		$date_livraison     Date de livraison
     *	@return     int         						<0 si ko, >0 si ok
     */
    function set_date_livraison($user, $date_livraison)
    {
        if ($user->rights->repair->creer)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."repair";
            $sql.= " SET date_livraison = ".($date_livraison ? "'".$this->db->idate($date_livraison)."'" : 'null');
            $sql.= " WHERE rowid = ".$this->id;

            dol_syslog("Repair::set_date_livraison sql=".$sql,LOG_DEBUG);
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->date_livraison = $date_livraison;
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog("Repair::set_date_livraison ".$this->error,LOG_ERR);
                return -1;
            }
        }
        else
        {
            return -2;
        }
    }

    /**
     *	Set availability
     *
     *	@param      User	$user		Object user making change
     *	@param      int		$id			If of availability delay
     *	@return     int           		<0 if KO, >0 if OK
     */
    function set_availability($user, $id)
    {
        if ($user->rights->repair->creer)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."repair ";
            $sql.= " SET fk_availability = '".$id."'";
            $sql.= " WHERE rowid = ".$this->id;

            if ($this->db->query($sql))
            {
                $this->fk_availability = $id;
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog("Repair::set_availability Erreur SQL");
                return -1;
            }
        }
    }

    /**
     *	Set source of demand
     *
     *	@param      User	$user		  	Object user making change
     *	@param      int		$id				Id of source
     *	@return     int           			<0 if KO, >0 if OK
     */
    function set_demand_reason($user, $id)
    {
        if ($user->rights->repair->creer)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."repair ";
            $sql.= " SET fk_input_reason = '".$id."'";
            $sql.= " WHERE rowid = ".$this->id;

            if ($this->db->query($sql))
            {
                $this->fk_input_reason = $id;
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog("Repair::set_demand_reason Erreur SQL");
                return -1;
            }
        }
    }

    /**
     *  Return list of orders (eventuelly filtered on a user) into an array
     *
     *  @param      int		$brouillon      0=non brouillon, 1=brouillon
     *  @param      User	$user           Objet user de filtre
     *  @return     int             		-1 if KO, array with result if OK
     */
    function liste_array($brouillon=0, $user='')
    {
        global $conf;

        $ga = array();

        $sql = "SELECT s.nom, s.rowid, c.rowid, c.ref";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."repair as c";
        $sql.= " WHERE c.entity = ".$conf->entity;
        $sql.= " AND c.fk_soc = s.rowid";
        if ($brouillon) $sql.= " AND c.fk_statut = 0";     //TODO brouillon
        if ($user) $sql.= " AND c.fk_user_author <> ".$user->id;
        $sql .= " ORDER BY c.date_repair DESC";

        $result=$this->db->query($sql);
        if ($result)
        {
            $numc = $this->db->num_rows($result);
            if ($numc)
            {
                $i = 0;
                while ($i < $numc)
                {
                    $obj = $this->db->fetch_object($result);

                    $ga[$obj->rowid] = $obj->ref;
                    $i++;
                }
            }
            return $ga;
        }
        else
        {
            dol_print_error($this->db);
            return -1;
        }
    }

    /**
     *	Change le delai de livraison
     *
     *	@param      int		$availability_id	Id du nouveau mode
     *	@return     int         				>0 if OK, <0 if KO
     */
    function availability($availability_id)
    {
        dol_syslog('Repair::availability('.$availability_id.')');
        if ($this->statut >= 0)
        {
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
            $sql .= ' SET fk_availability = '.$availability_id;
            $sql .= ' WHERE rowid='.$this->id;
            if ( $this->db->query($sql) )
            {
                $this->availability_id = $availability_id;
                return 1;
            }
            else
            {
                dol_syslog('Repair::availability Erreur '.$sql.' - '.$this->db->error(), LOG_ERR);
                $this->error=$this->db->lasterror();
                return -1;
            }
        }
        else
        {
            dol_syslog('Repair::availability, etat facture incompatible', LOG_ERR);
            $this->error='Etat repair incompatible '.$this->statut;
            return -2;
        }
    }

    /**
     *	Change la source de la demande
     *
     *  @param      int		$demand_reason_id	Id of new demand
     *  @return     int        			 		>0 if ok, <0 if ko
     */
    function demand_reason($demand_reason_id)
    {
        dol_syslog('Repair::demand_reason('.$demand_reason_id.')');
        if ($this->statut >= 0)
        {
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair';
            $sql .= ' SET fk_input_reason = '.$demand_reason_id;
            $sql .= ' WHERE rowid='.$this->id;
            if ( $this->db->query($sql) )
            {
                $this->demand_reason_id = $demand_reason_id;
                return 1;
            }
            else
            {
                dol_syslog('Repair::demand_reason Erreur '.$sql.' - '.$this->db->error(), LOG_ERR);
                $this->error=$this->db->lasterror();
                return -1;
            }
        }
        else
        {
            dol_syslog('Repair::demand_reason, etat facture incompatible', LOG_ERR);
            $this->error='Etat repair incompatible '.$this->statut;
            return -2;
        }
    }

    /**
     *	Set customer ref
     *
     *	@param      User	$user           User that make change
     *	@param      string	$ref_client     Customer ref
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_ref_client($user, $ref_client)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_ref_client this->id='.$this->id.', ref_client='.$ref_client);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            $sql.= ' ref_client = '.(empty($ref_client) ? 'NULL' : '\''.$this->db->escape($ref_client).'\'');
            $sql.= ' WHERE rowid = '.$this->id;

            if ($this->db->query($sql) )
            {
                $this->ref_client = $ref_client;
                return 1;
            }
            else
            {
                $this->error=$this->db->lasterror();
                dol_syslog('Repair::set_ref_client Erreur '.$this->error.' - '.$sql, LOG_ERR);
                return -2;
            }
        }
        else
        {
            return -1;
        }
    }

//<Tathar>
    /**
     *	Set trademark
     *
     *	@param      User	$user           User that make change
     *	@param      string	$trademark      trademark
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_trademark($user, $trademark)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_trademark this->id='.$this->id.', trademark='.$trademark);

			$fk_machine = $this->get_fkMachine();
			$objmac = new Machine($this->db);
			$objmac->fetch($fk_machine);
			$objmac->trademark = $trademark;
			$fk_machine = $objmac->create($user);

			if ($fk_machine > 0)
			{
            	$sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            	$sql.= ' fk_machine = '.$fk_machine;
            	$sql.= ' WHERE rowid = '.$this->id;

				dol_syslog('Repair::set_trademark sql= '.$sql , LOG_DEBUG);
	            if ($this->db->query($sql) )
	            {
	                $this->trademark = $trademark;
					$objmac->clean($user);
	                return 1;
	            }
	            else
	            {
	                $this->error=$this->db->lasterror();
	                dol_syslog('Repair::set_trademark Erreur '.$this->error.' - '.$sql, LOG_ERR);
	                return -2;
				}
			}
			else  return -3;
	        }
	    else
	   	{
           	return -1;
		}
    }

    /**
     *	Set type
     *
     *	@param      User	$user           User that make change
     *	@param      string	$type      		machine type
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_type_id($user, $type)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_type_id this->id='.$this->id.', type='.$type);

			$fk_machine = $this->get_fkMachine();

			$objmac = new Machine($this->db);
			$return = $objmac->setType_id($fk_machine, $type);
			if ($return == 1 ) $this->type_id= $type;
			return $return;

	    }
	    else
	   	{
           	return -1;
		}
    }

    /**
     *	Set model
     *
     *	@param      User	$user           User that make change
     *	@param      string	$trademark      trademark
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_model($user, $model)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_model this->id='.$this->id.', model='.$model);

			$fk_machine = $this->get_fkMachine();

			$objmac = new Machine($this->db);
			$objmac->fetch($fk_machine);
			$objmac->model = $model;
			$fk_machine = $objmac->create($user);

			if ($fk_machine > 0)
			{
            	$sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            	$sql.= ' fk_machine = '.$fk_machine;
            	$sql.= ' WHERE rowid = '.$this->id;

				dol_syslog('Repair::set_model sql= '.$sql , LOG_DEBUG);
	            if ($this->db->query($sql) )
	            {
	                $this->model = $model;
					$objmac->clean($user);
	                return 1;
	            }
	            else
	            {
	                $this->error=$this->db->lasterror();
	                dol_syslog('Repair::set_model Erreur '.$this->error.' - '.$sql, LOG_ERR);
	                return -2;
				}
			}
			else  return -3;
	        }
	    else
	   	{
           	return -1;
		}
    }

/**
     *	Set n_model
     *
     *	@param      User	$user           User that make change
     *	@param      string	$n_model      trademark
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_n_model($user, $n_model)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_n_model this->id='.$this->id.', n_model='.$n_model);

			$fk_machine = $this->get_fkMachine();

			$objmac = new Machine($this->db);
			$objmac->fetch($fk_machine);
			$objmac->n_model = $n_model;
			$fk_machine = $objmac->create($user);

			if ($fk_machine > 0)
			{
            	$sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            	$sql.= ' fk_machine = '.$fk_machine;
            	$sql.= ' WHERE rowid = '.$this->id;

				dol_syslog('Repair::set_n_model sql= '.$sql , LOG_DEBUG);
	            if ($this->db->query($sql) )
	            {
	                $this->model = $model;
					$objmac->clean($user);
	                return 1;
	            }
	            else
	            {
	                $this->error=$this->db->lasterror();
	                dol_syslog('Repair::n_model Erreur '.$this->error.' - '.$sql, LOG_ERR);
	                return -2;
				}
			}
			else  return -3;
	        }
	    else
	   	{
           	return -1;
		}
    }

	/**
     *	Set serial_num
     *
     *	@param      User	$user           User that make change
     *	@param      string	$serial_num     Serial Number
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_serial_num($user, $serial_num)
    {
       if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_serial_num this->id='.$this->id.', serial_num='.$serial_num);

            $objmac = new Machine($this->db);
			$fk_machine = $this->get_fkMachine();
            $objmac->setSerialNum( $fk_machine, $serial_num );
        }
        else
        {
            return -1;
        }

    }


	/**
     *	Set breakdown
     *
     *	@param      User	$user           User that make change
     *	@param      string	$breakdown      breakdown
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_breakdown($user, $breakdown)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_breakdown this->id='.$this->id.', breakdown='.$breakdown);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            $sql.= ' breakdown = '."'".$breakdown."'";
            $sql.= ' WHERE rowid = '.$this->id;

            if ($this->db->query($sql) )
            {
                $this->breakdown = $breakdown;
                return 1;
            }
            else
            {
                $this->error=$this->db->lasterror();
                dol_syslog('Repair::set_breakdown Erreur '.$this->error.' - '.$sql, LOG_ERR);
                return -2;
            }
        }
        else
        {
            return -1;
        }
    }


	/**
     *	Set support_id
     *
     *	@param      User	$user           User that make change
     *	@param      string	$support_id     support_id
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_support_id($user, $support_id)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_support_id this->id='.$this->id.', support_id='.$support_id);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            $sql.= ' support_id = '."'".$support_id."'";
            $sql.= ' WHERE rowid = '.$this->id;

            if ($this->db->query($sql) )
            {
                $this->support_id = $support_id;
                return 1;
            }
            else
            {
                $this->error=$this->db->lasterror();
                dol_syslog('Repair::set_support_id Erreur '.$this->error.' - '.$sql, LOG_ERR);
                return -2;
            }
        }
        else
        {
            return -1;
        }
    }

	/**
     *	Set set_accessory
     *
     *	@param      User	$user           User that make change
     *	@param      string	$accessory      accessory
     *	@return     int             		<0 if KO, >0 if OK
     */
    function set_accessory($user, $accessory)
    {
        if ($user->rights->repair->creer)
        {
            dol_syslog('Repair::set_accessory this->id='.$this->id.', accessory='.$accessory);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET';
            $sql.= ' accessory = '."'".$accessory."'";
            $sql.= ' WHERE rowid = '.$this->id;

            if ($this->db->query($sql) )
            {
                $this->accessory = $accessory;
                return 1;
            }
            else
            {
                $this->error=$this->db->lasterror();
                dol_syslog('Repair::set_accessory Erreur '.$this->error.' - '.$sql, LOG_ERR);
                return -2;
            }
        }
        else
        {
            return -1;
        }
    }


//</Tathar>

    /**
     *	Classify the order as invoiced
     *
     *	@return     int     <0 if ko, >0 if ok
     */
    function classer_facturee()
    {
        global $conf;

		if ($this->repair_statut < 4)
        {
			dol_syslog(get_class($this)."::cancel wrong status ".$this->repair_statut, LOG_WARNING);
            return 0;
        }

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'repair SET facture = 1';
        $sql .= ' WHERE rowid = '.$this->id.' AND repair_statut >= 4 ;';
		dol_syslog(get_class($this)."::classer_facturee sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql) )
        {
            if (! empty($conf->propal->enabled) && ! empty($conf->global->PROPALE_CLASSIFIED_INVOICED_WITH_ORDER))
            {
                $this->fetchObjectLinked('','propal',$this->id,$this->element);
                if (! empty($this->linkedObjects))
                {
                    foreach($this->linkedObjects['propal'] as $element)
                    {
                        $ret=$element->classer_facturee();
                    }
                }
            }
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            return -1;
        }
    }


    /**
     *  Update a line in database
     *
     *  @param    	int				$rowid            	Id of line to update
     *  @param    	string			$desc             	Description de la ligne
     *  @param    	double			$pu               	Prix unitaire
     *  @param    	double			$qty              	Quantity
     *  @param    	double			$remise_percent   	Pourcentage de remise de la ligne
     *  @param    	double			$txtva           	Taux TVA
     * 	@param		double			$txlocaltax1		Local tax 1 rate
     *  @param		double			$txlocaltax2		Local tax 2 rate
     *  @param    	string			$price_base_type	HT or TTC
     *  @param    	int				$info_bits        	Miscellaneous informations on line
     *  @param    	timestamp		$date_start        	Start date of the line
     *  @param    	timestamp		$date_end          	End date of the line
     * 	@param		int				$type				Type of line (0=product, 1=service)
     *  @param		int				$fk_parent_line		Parent line id
     *  @param		int				$skip_update_total	Skip update of total
     *  @return   	int              					< 0 if KO, > 0 if OK
     */
    function updateline($rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1=0,$txlocaltax2=0, $price_base_type='HT', $info_bits=0, $date_start='', $date_end='', $type=0, $fk_parent_line=0, $skip_update_total=0)
    {
        global $conf;

        dol_syslog("CustomerRepair::UpdateLine $rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $date_start, $date_end, $type");
        include_once(DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php');

        if ($this->brouillon)
        {
            $this->db->begin();

            // Clean parameters
            if (empty($qty)) $qty=0;
            if (empty($info_bits)) $info_bits=0;
            if (empty($txtva)) $txtva=0;
            if (empty($txlocaltax1)) $txlocaltax1=0;
            if (empty($txlocaltax2)) $txlocaltax2=0;
            if (empty($remise)) $remise=0;
            if (empty($remise_percent)) $remise_percent=0;
            $remise_percent=price2num($remise_percent);
            $qty=price2num($qty);
            $pu = price2num($pu);
            $txtva=price2num($txtva);
            $txlocaltax1=price2num($txlocaltax1);
            $txlocaltax2=price2num($txlocaltax2);

            // Calcul du total TTC et de la TVA pour la ligne a partir de
            // qty, pu, remise_percent et txtva
            // TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
            // la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
            $tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
            $total_ht  = $tabprice[0];
            $total_tva = $tabprice[1];
            $total_ttc = $tabprice[2];
            $total_localtax1 = $tabprice[9];
            $total_localtax2 = $tabprice[10];

            // Anciens indicateurs: $price, $subprice, $remise (a ne plus utiliser)
            $price = $pu;
            $subprice = $pu;
            $remise = 0;
            if ($remise_percent > 0)
            {
                $remise = round(($pu * $remise_percent / 100),2);
                $price = ($pu - $remise);
            }

            // Update line
            $this->line=new RepairLine($this->db);

            // Stock previous line records
            $staticline=new RepairLine($this->db);
            $staticline->fetch($rowid);
            $this->line->oldline = $staticline;

            // Reorder if fk_parent_line change
            if (! empty($fk_parent_line) && ! empty($staticline->fk_parent_line) && $fk_parent_line != $staticline->fk_parent_line)
            {
            	$rangmax = $this->line_max($fk_parent_line);
            	$this->line->rang = $rangmax + 1;
            }

            $this->line->rowid=$rowid;
            $this->line->desc=$desc;
            $this->line->qty=$qty;
            $this->line->tva_tx=$txtva;
            $this->line->localtax1_tx=$txlocaltax1;
            $this->line->localtax2_tx=$txlocaltax2;
            $this->line->remise_percent=$remise_percent;
            $this->line->subprice=$subprice;
            $this->line->info_bits=$info_bits;
            $this->line->special_code=0;	// To remove special_code=3 coming from proposals copy
            $this->line->total_ht=$total_ht;
            $this->line->total_tva=$total_tva;
            $this->line->total_localtax1=$total_localtax1;
            $this->line->total_localtax2=$total_localtax2;
            $this->line->total_ttc=$total_ttc;
            $this->line->date_start=$date_start;
            $this->line->date_end=$date_end;
            $this->line->product_type=$type;
            $this->line->fk_parent_line=$fk_parent_line;
            $this->line->skip_update_total=$skip_update_total;

            // TODO deprecated
            $this->line->price=$price;
            $this->line->remise=$remise;

            $result=$this->line->update();
            if ($result > 0)
            {
            	// Reorder if child line
            	if (! empty($fk_parent_line)) $this->line_order(true,'DESC');

                // Mise a jour info denormalisees
                $this->update_price(1);

                $this->db->commit();
                return $result;
            }
            else
            {
                $this->error=$this->db->error();
                $this->db->rollback();
                dol_syslog("CustomerRepair::UpdateLine Error=".$this->error, LOG_ERR);
                return -1;
            }
        }
        else
        {
            $this->error="CustomerRepair::Updateline Repair status makes operation forbidden";
            return -2;
        }
    }

    /**
     *	Load indicators for dashboard (this->nbtodo and this->nbtodolate)
     *
     *	@param		User	$user   Object user
     *	@return     int     		<0 if KO, >0 if OK
     */
    function load_board($user)
    {
        global $conf, $user;

        $now=dol_now();

        $this->nbtodo=$this->nbtodolate=0;
        $clause = " WHERE";

        $sql = "SELECT c.rowid, c.date_creation as datec, c.fk_statut";
        $sql.= " FROM ".MAIN_DB_PREFIX."repair as c";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
        {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc";
            $sql.= " WHERE sc.fk_user = " .$user->id;
            $clause = " AND";
        }
        $sql.= $clause." c.entity = ".$conf->entity;
        //$sql.= " AND c.fk_statut IN (1,2,3) AND c.facture = 0";
        $sql.= " AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))";    // If status is 2 and facture=1, it must be selected
        if ($user->societe_id) $sql.=" AND c.fk_soc = ".$user->societe_id;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $this->nbtodo++;
                if ($obj->fk_statut != 3 && $this->db->jdate($obj->datec) < ($now - $conf->repair->client->warning_delay)) $this->nbtodolate++;
            }
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            return -1;
        }
    }


	/**
	 *    Return label of a Support code
	 *
	 *    @return     string      Translated name of a Support code
	 */
	function getSupportLabel()
	{
//		global $langs;
//		$langs->load("dict");

//		$code=$this->support_id;
//        return $langs->trans("Support".$code)!="Support".$code ? $langs->trans("Support".$code) : '';

		$sql = 'SELECT';
        $sql.= ' label';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'c_repair_support';
        $sql.= ' WHERE code = '."'".$this->support_id."'";
		dol_syslog(get_class($this)."::getSupportLabel sql=".$sql);
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $label = $obj->label;
            }
            $this->db->free($result);
			return $label;
		}
	}

    /**
     *	Return source label of order
     *
     *	@return     string      Label
     */
    function getLabelSource()
    {
        global $langs;

        $label=$langs->trans('RepairSource'.$this->source);

        if ($label == 'RepairSource') return '';
        return $label;
    }

    /**
     *	Return status label of Repair
     *
     *	@param      int		$mode       0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
     *	@return     string      		Libelle
     */
    function getLibStatut($mode)
    {
        return $this->LibStatut($this->repair_statut,$this->facturee,$mode);
    }

    /**
     *	Return label of status
     *
     *	@param		int		$statut      	Id statut
     *  @param      int		$facturee    	if invoiced
     *	@param      int		$mode        	0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
     *  @return     string					Label of status
     */

    function LibStatut($repair_statut,$facturee,$mode)
    {
        global $langs;
        //print 'x'.$statut.'-'.$facturee;
        if ($mode == 0) //libelle long
        {
            if ($repair_statut==-2) return $langs->trans('StatusRepairRefusedEstimate');
            if ($repair_statut==-1) return $langs->trans('StatusRepairCanceled');
            if ($repair_statut==0) return $langs->trans('StatusRepairWaitingEstimate');
            if ($repair_statut==1) return $langs->trans('StatusRepairEstimatesDuring');
            if ($repair_statut==2) return $langs->trans('StatusRepairEstimatesComplete');
            if ($repair_statut==3) return $langs->trans('StatusRepairEstimatesValidate');
            if ($repair_statut==4) return $langs->trans('StatusRepairAcceptedEstimate');
            if ($repair_statut==5) return $langs->trans('StatusRepairInProgress');
            if ($repair_statut==6) return $langs->trans('StatusRepairComplete');
            if ($repair_statut==7) return $langs->trans('StatusRepairValidate');
            if ($repair_statut==8 && ! $facturee) return $langs->trans('StatusRepairToBill');
            if ($repair_statut==8 && $facturee) return $langs->trans('StatusRepairProcessed');
        }
        elseif ($mode == 1) //libelle court
        {
            if ($repair_statut==-2) return $langs->trans('StatusRepairRefusedEstimateShort');
            if ($repair_statut==-1) return $langs->trans('StatusRepairCanceledShort');
            if ($repair_statut==0) return $langs->trans('StatusRepairWaitingEstimateShort');
            if ($repair_statut==1) return $langs->trans('StatusRepairEstimatesDuringShort');
            if ($repair_statut==2) return $langs->trans('StatusRepairEstimatesCompleteShort');
            if ($repair_statut==3) return $langs->trans('StatusRepairEstimatesValidateShort');
            if ($repair_statut==4) return $langs->trans('StatusRepairAcceptedEstimateShort');
            if ($repair_statut==5) return $langs->trans('StatusRepairInProgressShort');
            if ($repair_statut==6) return $langs->trans('StatusRepairCompleteShort');
            if ($repair_statut==7) return $langs->trans('StatusRepairValidateShort');
            if ($repair_statut==8 && ! $facturee) return $langs->trans('StatusRepairToBillShort');
            if ($repair_statut==8 && $facturee) return $langs->trans('StatusRepairProcessed');
        }
        elseif ($mode == 2) //Picto + Libelle court
        {
            if ($repair_statut==-2) return img_picto($langs->trans('StatusRepairCanceled'),'statut5').' '.$langs->trans('StatusRepairRefusedEstimateShort');
            if ($repair_statut==-1) return img_picto($langs->trans('StatusRepairCanceled'),'statut5').' '.$langs->trans('StatusRepairCanceledShort');
            if ($repair_statut==0) return img_picto($langs->trans('StatusRepairWaitingEstimate'),'statut0').' '.$langs->trans('StatusRepairWaitingEstimateShort');
            if ($repair_statut==1) return img_picto($langs->trans('StatusRepairEstimatesDuring'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesDuringShort');
            if ($repair_statut==2) return img_picto($langs->trans('StatusRepairEstimatesComplete'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesCompleteShort');
            if ($repair_statut==3) return img_picto($langs->trans('StatusRepairEstimatesValidate'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesValidateShort');
            if ($repair_statut==4) return img_picto($langs->trans('StatusRepairAcceptedEstimate'),'statut1').' '.$langs->trans('StatusRepairAcceptedEstimateShort');
            if ($repair_statut==5) return img_picto($langs->trans('StatusRepairInProgress'),'statut3').' '.$langs->trans('StatusRepairInProgressShort');
            if ($repair_statut==6) return img_picto($langs->trans('StatusRepairComplete'),'statut4').' '.$langs->trans('StatusRepairCompleteShort');
            if ($repair_statut==7) return img_picto($langs->trans('StatusRepairValidate'),'statut4').' '.$langs->trans('StatusRepairValidateShort');
            if ($repair_statut==8 && ! $facturee) return img_picto($langs->trans('StatusRepairToBill'),'statut7').' '.$langs->trans('StatusRepairToBillShort');
            if ($repair_statut==8 && $facturee) return img_picto($langs->trans('StatusRepairProcessed'),'statut6').' '.$langs->trans('StatusRepairProcessedShort');
        }
        elseif ($mode == 3) //Picto
        {
            if ($repair_statut==-2) return img_picto($langs->trans('StatusRepairRefusedEstimate'),'statut5');
            if ($repair_statut==-1) return img_picto($langs->trans('StatusRepairCanceled'),'statut5');
            if ($repair_statut==0) return img_picto($langs->trans('StatusRepairWaitingEstimate'),'statut0');
            if ($repair_statut==1) return img_picto($langs->trans('StatusRepairEstimatesDuring'),'statut2@repair');
            if ($repair_statut==2) return img_picto($langs->trans('StatusRepairEstimatesComplete'),'statut2@repair');
            if ($repair_statut==3) return img_picto($langs->trans('StatusRepairEstimatesValidate'),'statut2@repair');
            if ($repair_statut==4) return img_picto($langs->trans('StatusRepairAcceptedEstimate'),'statut1');
            if ($repair_statut==5) return img_picto($langs->trans('StatusRepairInProgress'),'statut3');
            if ($repair_statut==6) return img_picto($langs->trans('StatusRepairComplete'),'statut4');
            if ($repair_statut==7) return img_picto($langs->trans('StatusRepairValidate'),'statut4');
            if ($repair_statut==8 && ! $facturee) return img_picto($langs->trans('StatusRepairToBill'),'statut7');
            if ($repair_statut==8 && $facturee) return img_picto($langs->trans('StatusRepairProcessed'),'statut6');
        }
        elseif ($mode == 4) //Picto + Libelle long
        {
            if ($repair_statut==-2) return img_picto($langs->trans('StatusRepairRefusedEstimate'),'statut5').' '.$langs->trans('StatusRepairRefusedEstimate');
            if ($repair_statut==-1) return img_picto($langs->trans('StatusRepairCanceled'),'statut5').' '.$langs->trans('StatusRepairCanceled');
            if ($repair_statut==0) return img_picto($langs->trans('StatusRepairWaitingEstimate'),'statut0').' '.$langs->trans('StatusRepairWaitingEstimate');
            if ($repair_statut==1) return img_picto($langs->trans('StatusRepairEstimatesDuring'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesDuring');
            if ($repair_statut==2) return img_picto($langs->trans('StatusRepairEstimatesComplete'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesComplete');
            if ($repair_statut==3) return img_picto($langs->trans('StatusRepairEstimatesValidate'),'statut2@repair').' '.$langs->trans('StatusRepairEstimatesValidate');
            if ($repair_statut==4) return img_picto($langs->trans('StatusRepairAcceptedEstimate'),'statut1').' '.$langs->trans('StatusRepairAcceptedEstimate');
            if ($repair_statut==5) return img_picto($langs->trans('StatusRepairInProgress'),'statut3').' '.$langs->trans('StatusRepairInProgress');
            if ($repair_statut==6) return img_picto($langs->trans('StatusRepairComplete'),'statut4').' '.$langs->trans('StatusRepairComplete');
            if ($repair_statut==7) return img_picto($langs->trans('StatusRepairValidate'),'statut4').' '.$langs->trans('StatusRepairValidate');
            if ($repair_statut==8 && ! $facturee) return img_picto($langs->trans('StatusRepairToBill'),'statut7').' '.$langs->trans('StatusRepairToBill');
            if ($repair_statut==8 && $facturee) return img_picto($langs->trans('StatusRepairProcessed'),'statut6').' '.$langs->trans('StatusRepairProcessed');
        }
        elseif ($mode == 5) //Libelle court + Picto
        {
            if ($repair_statut==-2) return $langs->trans('StatusRepairRefusedEstimateShort').' '.img_picto($langs->trans('StatusRepairRefusedEstimate'),'statut5');
            if ($repair_statut==-1) return $langs->trans('StatusRepairCanceledShort').' '.img_picto($langs->trans('StatusRepairCanceled'),'statut5');
            if ($repair_statut==0) return $langs->trans('StatusRepairWaitingEstimateShort').' '.img_picto($langs->trans('StatusRepairWaitingEstimate'),'statut0');
            if ($repair_statut==1) return $langs->trans('StatusRepairEstimatesDuringShort').' '.img_picto($langs->trans('StatusRepairEstimatesDuring'),'statut2@repair');
            if ($repair_statut==2) return $langs->trans('StatusRepairEstimatesCompleteShort').' '.img_picto($langs->trans('StatusRepairEstimatesComplete'),'statut2@repair');
            if ($repair_statut==3) return $langs->trans('StatusRepairEstimatesValidateShort').' '.img_picto($langs->trans('StatusRepairEstimatesValidate'),'statut2@repair');
            if ($repair_statut==4) return $langs->trans('StatusRepairAcceptedEstimateShort').' '.img_picto($langs->trans('StatusRepairAcceptedEstimate'),'statut1');
            if ($repair_statut==5) return $langs->trans('StatusRepairInProgressShort').' '.img_picto($langs->trans('StatusRepairInProgress'),'statut3');
            if ($repair_statut==6) return $langs->trans('StatusRepairCompleteShort').' '.img_picto($langs->trans('StatusRepairComplete'),'statut4');
            if ($repair_statut==7) return $langs->trans('StatusRepairValidate').' '.img_picto($langs->trans('StatusRepairValidate'),'statut4');
            if ($repair_statut==8 && ! $facturee) return $langs->trans('StatusRepairToBillShort').' '.img_picto($langs->trans('StatusRepairToBill'),'statut7');
            if ($repair_statut==8 && $facturee) return $langs->trans('StatusRepairProcessedShort').' '.img_picto($langs->trans('StatusRepairProcessed'),'statut6');
        }
    }


    /**
     *	Return clicable link of object (with eventually picto)
     *
     *	@param      int			$withpicto      Add picto into link
     *	@param      int			$option         Where point the link
     *	@param      int			$max          	Max length to show
     *	@param      int			$short			Use short labels
     *	@return     string          		String with URL
     */
    function getNomUrl($withpicto=0,$option=0,$max=0,$short=0)
    {
        global $conf, $langs;

        $result='';

/*        if ($conf->expedition->enabled && ($option == 1 || $option == 2)) $url = DOL_URL_ROOT.'/expedition/shipment.php?id='.$this->id;
        else*/ $url = DOL_URL_ROOT.'/repair/fiche.php?id='.$this->id;

        if ($short) return $url;

        $linkstart = '<a href="'.$url.'">';
        $linkend='</a>';

        $picto='repair@repair';
        $label=$langs->trans("ShowRepair").': '.$this->ref;

        if ($withpicto) $result.=($linkstart.img_object($label,$picto).$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        $result.=$linkstart.$this->ref.$linkend;
        return $result;
    }


    /**
     *	Charge les informations d'ordre info dans l'objet repair
     *
     *	@param  int		$id       Id of order
     *	@return	void
     */
    function info($id)
    {
        $sql = 'SELECT c.rowid, date_creation as datec, tms as datem,';
        $sql.= ' date_valid_e as datev_e,';
        $sql.= ' date_valid_r as datev_r,';
        $sql.= ' date_cloture as datecloture,';
        $sql.= ' fk_user_author, fk_user_valid_e, fk_user_valid_r, fk_user_cloture';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'repair as c';
        $sql.= ' WHERE c.rowid = '.$id;
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $this->id = $obj->rowid;
                if ($obj->fk_user_author)
                {
                    $cuser = new User($this->db);
                    $cuser->fetch($obj->fk_user_author);
                    $this->user_creation   = $cuser;
                }

                if ($obj->fk_user_valid_e)
                {
                    $euser = new User($this->db);
                    $euser->fetch($obj->fk_user_valid_e);
                    $this->user_validation = $euser;
                }

                if ($obj->fk_user_valid_r)
                {
                    $ruser = new User($this->db);
                    $ruser->fetch($obj->fk_user_valid_r);
                    $this->user_approve = $ruser;
                }

                if ($obj->fk_user_cloture)
                {
                    $cluser = new User($this->db);
                    $cluser->fetch($obj->fk_user_cloture);
                    $this->user_cloture   = $cluser;
                }

                $this->date_creation     = $this->db->jdate($obj->datec);
                $this->date_modification = $this->db->jdate($obj->datem);
                $this->date_validation   = $this->db->jdate($obj->datev_e);
                $this->date_approve   = $this->db->jdate($obj->datev_r);
                $this->date_cloture      = $this->db->jdate($obj->datecloture);
            }

            $this->db->free($result);

        }
        else
        {
            dol_print_error($this->db);
        }
    }


    /**
     *  Initialise an instance with random values.
     *  Used to build previews or test instances.
     *	id must be 0 if object instance is a specimen.
     *
     *  @return	void
     */
    function initAsSpecimen()
    {
        global $user,$langs,$conf;

        dol_syslog(get_class($this)."::initAsSpecimen");

        // Charge tableau des produits prodids
        $prodids = array();
        $sql = "SELECT rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."product";
        $sql.= " WHERE entity IN (".getEntity('product', 1).")";
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num_prods = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num_prods)
            {
                $i++;
                $row = $this->db->fetch_row($resql);
                $prodids[$i] = $row[0];
            }
        }

		$objmac = new Machine($this->db);
		$objmac->initAsSpecimen();
        // Initialise parametres
        $this->id=0;
        $this->ref = 'SPECIMEN';
        $this->specimen=1;
        $this->socid = 1;
		$this->trademark = $objmac->trademark;
		$this->model = $objmac->model;
		$this->type_id = $objmac->type_id;
		$this->n_model = $objmac->n_model;
		$this->serial_num = $objmac->serial_num;
		$this->breakdown = "ne fonctionne plus";
		$this->support_id = "Mag";
		$this->accessory = "cordon d'alimentation";
        $this->date = time();
        $this->date_lim_reglement=$this->date+3600*24*30;
        $this->cond_reglement_code = 'RECEP';
        $this->mode_reglement_code = 'CHQ';
        $this->availability_code   = 'DSP';
        $this->demand_reason_code  = 'SRC_00';
        $this->note_public='This is a comment (public)';
        $this->note='This is a comment (private)';
        // Lines
        $nbp = 5;
        $xnbp = 0;
        while ($xnbp < $nbp)
        {
            $line=new RepairLine($this->db);

            $line->desc=$langs->trans("Description")." ".$xnbp;
            $line->qty=1;
            $line->subprice=100;
            $line->price=100;
            $line->tva_tx=19.6;
            if ($xnbp == 2)
            {
                $line->total_ht=50;
                $line->total_ttc=59.8;
                $line->total_tva=9.8;
                $line->remise_percent=50;
            }
            else
            {
                $line->total_ht=100;
                $line->total_ttc=119.6;
                $line->total_tva=19.6;
                $line->remise_percent=00;
            }
            $prodid = rand(1, $num_prods);
            $line->fk_product=$prodids[$prodid];

            $this->lines[$xnbp]=$line;

            $this->total_ht       += $line->total_ht;
            $this->total_tva      += $line->total_tva;
            $this->total_ttc      += $line->total_ttc;

            $xnbp++;
        }
    }


    /**
     *	Charge indicateurs this->nb de tableau de bord
     *
     *	@return     int         <0 si ko, >0 si ok
     */
    function load_state_board()
    {
        global $conf, $user;

        $this->nb=array();
        $clause = "WHERE";

        $sql = "SELECT count(co.rowid) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX."repair as co";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON co.fk_soc = s.rowid";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
        {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
            $sql.= " WHERE sc.fk_user = " .$user->id;
            $clause = "AND";
        }
        $sql.= " ".$clause." co.entity = ".$conf->entity;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $this->nb["orders"]=$obj->nb;
            }
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            $this->error=$this->db->error();
            return -1;
        }
    }

    /**
     * 	Return an array of order lines
     *
     * @return	array		Lines of order
     */
    function getLinesArray()
    {
        $lines = array();

        $sql = 'SELECT l.rowid, l.fk_product, l.product_type, l.label as custom_label, l.description, l.price, l.qty, l.tva_tx, ';
        $sql.= ' l.fk_remise_except, l.remise_percent, l.subprice, l.info_bits, l.rang, l.special_code, l.fk_parent_line,';
        $sql.= ' l.total_ht, l.total_tva, l.total_ttc, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.localtax1_tx, l.localtax2_tx,';
        $sql.= ' l.date_start, l.date_end,';
        $sql.= ' p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid, ';
        $sql.= ' p.description as product_desc, p.stock as stock_reel';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'repairdet as l';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product=p.rowid';
        $sql.= ' WHERE l.fk_repair = '.$this->id;
        $sql.= ' ORDER BY l.rang ASC, l.rowid';

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);

                $this->lines[$i]->id				= $obj->rowid;
                $this->lines[$i]->label 			= $obj->custom_label;
                $this->lines[$i]->description 		= $obj->description;
                $this->lines[$i]->fk_product		= $obj->fk_product;
                $this->lines[$i]->ref				= $obj->ref;
                $this->lines[$i]->product_label		= $obj->product_label;
                $this->lines[$i]->product_desc		= $obj->product_desc;
                $this->lines[$i]->fk_product_type	= $obj->fk_product_type;
                $this->lines[$i]->product_type		= $obj->product_type;
                $this->lines[$i]->qty				= $obj->qty;
                $this->lines[$i]->subprice			= $obj->subprice;
                $this->lines[$i]->fk_remise_except 	= $obj->fk_remise_except;
                $this->lines[$i]->remise_percent	= $obj->remise_percent;
                $this->lines[$i]->tva_tx			= $obj->tva_tx;
                $this->lines[$i]->info_bits			= $obj->info_bits;
                $this->lines[$i]->total_ht			= $obj->total_ht;
                $this->lines[$i]->total_tva			= $obj->total_tva;
                $this->lines[$i]->total_ttc			= $obj->total_ttc;
                $this->lines[$i]->fk_parent_line	= $obj->fk_parent_line;
                $this->lines[$i]->special_code		= $obj->special_code;
				$this->lines[$i]->stock				= $obj->stock_reel;
                $this->lines[$i]->rang				= $obj->rang;
                $this->lines[$i]->date_start		= $this->db->jdate($obj->date_start);
                $this->lines[$i]->date_end			= $this->db->jdate($obj->date_end);
				$this->lines[$i]->fk_fournprice		= $obj->fk_fournprice;
				$marginInfos						= getMarginInfos($obj->subprice, $obj->remise_percent, $obj->tva_tx, $obj->localtax1_tx, $obj->localtax2_tx, $this->lines[$i]->fk_fournprice, $obj->pa_ht);
				$this->lines[$i]->pa_ht				= $marginInfos[0];
				$this->lines[$i]->marge_tx			= $marginInfos[1];
				$this->lines[$i]->marque_tx			= $marginInfos[2];

                $i++;
            }

            $this->db->free($resql);

            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
            return -1;
        }
    }

	/**
     *	Get fk_machine
     *
     *	@return		fk_machine		<0 if ko, >0 if ok
     */
	function get_fkMachine()
	{
//		$this->db->begin();
		$sql = "SELECT fk_machine";
        $sql.= " FROM ".MAIN_DB_PREFIX."repair";
        $sql.= " WHERE rowid = ".$this->id;

		dol_syslog(get_class($this)."::getFkMachine sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
				dol_syslog(get_class($this)."::getFkMachine fk_machine=".$obj->fk_machine, LOG_DEBUG);
                return $obj->fk_machine;
            }
            return 0;
        }
        else
        {
            dol_print_error($db);
            $this->error=$this->db->error();
            return -1;
        }
	}

    /**
     *    	Return a link on thirdparty (with picto)
     *
     *		@param	int		$withpicto		Add picto into link (0=No picto, 1=Include picto with link, 2=Picto only)
     *		@param	string	$option			Target of link ('', 'customer', 'prospect', 'supplier')
     *		@param	int		$maxlen			Max length of text
     *		@return	string					String with URL
     */
    function getThirdpartyUrl($withpicto=0,$socid=0,$maxlen=0)
    {
        global $conf,$langs;

		$objsoc = new Societe($this->db);
		if ($socid==0 && empty($this->socid) ) 
		{
			dol_syslog(get_class($this).":getThirdpartyUrl use ".get_class($this).":fetch(id) or ".get_class($this).":getThirdpartyUrl() with socid not null",LOG_ERR);
			return -1;
		}
		if ($socid==0) $socid = $this->socid;
		$objsoc->fetch($socid);

		$name=$objsoc->name;
        $result='';
        $lien=$lienfin='';

        $lien = '<a href="'.DOL_URL_ROOT.'/repair/thirdparty.php?id='.$socid;
        // Add type of canvas
        $lien.=(!empty($objsoc->canvas)?'&amp;canvas='.$objsoc->canvas:'').'">';
        $lienfin='</a>';
        if ($withpicto) $result.=($lien.img_object($langs->trans("ShowCompany").': '.$name,'company').$lienfin);
        if ($withpicto && $withpicto != 2) $result.=' ';
        $result.=$lien.($maxlen?dol_trunc($name,$maxlen):$name).$lienfin;
        return $result;
    }

}


/**
 *  \class      RepairLine
 *  \brief      Classe de gestion des lignes de repair
 */
class RepairLine
{
    var $db;
    var $error;

    var $oldline;

    // From llx_repairdet
    var $rowid;
    var $fk_parent_line;
    var $fk_facture;
    var $label;
    var $desc;          	// Description ligne
    var $fk_product;		// Id produit predefini
    var $product_type = 0;	// Type 0 = product, 1 = Service

    var $qty;				// Quantity (example 2)
    var $tva_tx;			// VAT Rate for product/service (example 19.6)
    var $localtax1_tx; 		// Local tax 1
    var $localtax2_tx; 		// Local tax 2
    var $subprice;      	// U.P. HT (example 100)
    var $remise_percent;	// % for line discount (example 20%)
    var $fk_remise_except;
    var $rang = 0;
	var $fk_fournprice;
	var $pa_ht;
    var $marge_tx;
    var $marque_tx;
    var $info_bits = 0;		// Bit 0: 	0 si TVA normal - 1 si TVA NPR
						    // Bit 1:	0 ligne normale - 1 si ligne de remise fixe
    var $special_code = 0;
    var $total_ht;			// Total HT  de la ligne toute quantite et incluant la remise ligne
    var $total_tva;			// Total TVA  de la ligne toute quantite et incluant la remise ligne
    var $total_localtax1;   // Total local tax 1 for the line
    var $total_localtax2;   // Total local tax 2 for the line
    var $total_ttc;			// Total TTC de la ligne toute quantite et incluant la remise ligne

    // Ne plus utiliser
    var $remise;
    var $price;

    // From llx_product
    var $ref;				// Reference produit
    var $libelle;			// deprecated
    var $product_ref;
    var $product_label; 	// Label produit
    var $product_desc;  	// Description produit

    // Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
    // Start and end date of the line
    var $date_start;
    var $date_end;

    var $skip_update_total; // Skip update price total for special lines


    /**
     *      Constructor
     *
     *      @param     DoliDB	$DB      handler d'acces base de donnee
     */
    function RepairLine($DB)
    {
        $this->db= $DB;
    }

    /**
     *  Load line order
     *
     *  @param  int		$rowid          Id line order
     *  @return	int						<0 if KO, >0 if OK
     */
    function fetch($rowid)
    {
        $sql = 'SELECT cd.rowid, cd.fk_repair, cd.fk_parent_line, cd.fk_product, cd.product_type, cd.label as custom_label, cd.description, cd.price, cd.qty, cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx,';
        $sql.= ' cd.remise, cd.remise_percent, cd.fk_remise_except, cd.subprice,';
        $sql.= ' cd.info_bits, cd.total_ht, cd.total_tva, cd.total_localtax1, cd.total_localtax2, cd.total_ttc, cd.fk_product_fournisseur_price as fk_fournprice, cd.buy_price_ht as pa_ht, cd.rang, cd.special_code,';
        $sql.= ' p.ref as product_ref, p.label as product_libelle, p.description as product_desc,';
        $sql.= ' cd.date_start, cd.date_end';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'repairdet as cd';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON cd.fk_product = p.rowid';
        $sql.= ' WHERE cd.rowid = '.$rowid;
        $result = $this->db->query($sql);
        if ($result)
        {
            $objp = $this->db->fetch_object($result);
            $this->rowid            = $objp->rowid;
            $this->fk_repair        = $objp->fk_repair;
            $this->fk_parent_line   = $objp->fk_parent_line;
            $this->label            = $objp->custom_label;
            $this->desc             = $objp->description;
            $this->qty              = $objp->qty;
            $this->price            = $objp->price;
            $this->subprice         = $objp->subprice;
            $this->tva_tx           = $objp->tva_tx;
            $this->localtax1_tx		= $objp->localtax1_tx;
            $this->localtax2_tx		= $objp->localtax2_tx;
            $this->remise           = $objp->remise;
            $this->remise_percent   = $objp->remise_percent;
            $this->fk_remise_except = $objp->fk_remise_except;
            $this->fk_product       = $objp->fk_product;
            $this->product_type     = $objp->product_type;
            $this->info_bits        = $objp->info_bits;
            $this->total_ht         = $objp->total_ht;
            $this->total_tva        = $objp->total_tva;
            $this->total_localtax1  = $objp->total_localtax1;
            $this->total_localtax2  = $objp->total_localtax2;
            $this->total_ttc        = $objp->total_ttc;
			$this->fk_fournprice	= $objp->fk_fournprice;
			$marginInfos			= getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $this->fk_fournprice, $objp->pa_ht);
			$this->pa_ht			= $marginInfos[0];
			$this->marge_tx			= $marginInfos[1];
			$this->marque_tx		= $marginInfos[2];
            $this->special_code		= $objp->special_code;
            $this->rang             = $objp->rang;

            $this->ref				= $objp->product_ref;      // deprecated
            $this->product_ref		= $objp->product_ref;
            $this->libelle			= $objp->product_libelle;  // deprecated
            $this->product_label	= $objp->product_libelle;
            $this->product_desc     = $objp->product_desc;

            $this->date_start       = $this->db->jdate($objp->date_start);
            $this->date_end         = $this->db->jdate($objp->date_end);

            $this->db->free($result);
        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     * 	Delete line in database
     *
     *	@return	 int  <0 si ko, >0 si ok
     */
    function delete()
    {
        global $conf, $user, $langs;

		$error=0;

        $sql = 'DELETE FROM '.MAIN_DB_PREFIX."repairdet WHERE rowid='".$this->rowid."';";

        dol_syslog("RepairLine::delete sql=".$sql);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            // Appel des triggers
            include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('LINEORDER_DELETE',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers

            return 1;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_syslog("RepairLine::delete ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *	Insert line into database
     *
     *	@param      int		$notrigger		1 = disable triggers
     *	@return		int						<0 if KO, >0 if OK
     */
    function insert($notrigger=0)
    {
        global $langs, $conf, $user;

		$error=0;

        dol_syslog("RepairLine::insert rang=".$this->rang);

        // Clean parameters
        if (empty($this->tva_tx)) $this->tva_tx=0;
        if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
        if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
        if (empty($this->total_localtax1)) $this->total_localtax1=0;
        if (empty($this->total_localtax2)) $this->total_localtax2=0;
        if (empty($this->rang)) $this->rang=0;
        if (empty($this->remise)) $this->remise=0;
        if (empty($this->remise_percent)) $this->remise_percent=0;
        if (empty($this->info_bits)) $this->info_bits=0;
        if (empty($this->special_code)) $this->special_code=0;
        if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

		if (empty($this->pa_ht)) $this->pa_ht=0;

		// si prix d'achat non renseigne et utilise pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}

        // Check parameters
        if ($this->product_type < 0) return -1;

        $this->db->begin();

        // Insertion dans base de la ligne
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'repairdet';
        $sql.= ' (fk_repair, fk_parent_line, label, description, qty, tva_tx, localtax1_tx, localtax2_tx,';
        $sql.= ' fk_product, product_type, remise_percent, subprice, price, remise, fk_remise_except,';
        $sql.= ' special_code, rang, fk_product_fournisseur_price, buy_price_ht,';
        $sql.= ' info_bits, total_ht, total_tva, total_localtax1, total_localtax2, total_ttc, date_start, date_end)';
        $sql.= " VALUES (".$this->fk_repair.",";
        $sql.= " ".($this->fk_parent_line>0?"'".$this->fk_parent_line."'":"null").",";
        $sql.= " ".(! empty($this->label)?"'".$this->db->escape($this->label)."'":"null").",";
        $sql.= " '".$this->db->escape($this->desc)."',";
        $sql.= " '".price2num($this->qty)."',";
        $sql.= " '".price2num($this->tva_tx)."',";
        $sql.= " '".price2num($this->localtax1_tx)."',";
        $sql.= " '".price2num($this->localtax2_tx)."',";
        $sql.= ' '.(! empty($this->fk_product)?$this->fk_product:"null").',';
        $sql.= " '".$this->product_type."',";
        $sql.= " '".price2num($this->remise_percent)."',";
        $sql.= " ".($this->subprice!=''?"'".price2num($this->subprice)."'":"null").",";
        $sql.= " ".($this->price!=''?"'".price2num($this->price)."'":"null").",";
        $sql.= " '".price2num($this->remise)."',";
        $sql.= ' '.(! empty($this->fk_remise_except)?$this->fk_remise_except:"null").',';
        $sql.= ' '.$this->special_code.',';
        $sql.= ' '.$this->rang.',';
		$sql.= ' '.(! empty($this->fk_fournprice)?$this->fk_fournprice:"null").',';
		$sql.= ' '.price2num($this->pa_ht).',';
        $sql.= " '".$this->info_bits."',";
        $sql.= " '".price2num($this->total_ht)."',";
        $sql.= " '".price2num($this->total_tva)."',";
        $sql.= " '".price2num($this->total_localtax1)."',";
        $sql.= " '".price2num($this->total_localtax2)."',";
        $sql.= " '".price2num($this->total_ttc)."',";
        $sql.= " ".(! empty($this->date_start)?"'".$this->db->idate($this->date_start)."'":"null").',';
        $sql.= " ".(! empty($this->date_end)?"'".$this->db->idate($this->date_end)."'":"null");
        $sql.= ')';

        dol_syslog(get_class($this)."::insert sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.'repairdet');

            if (! $notrigger)
            {
                // Appel des triggers
                include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('LINEORDER_INSERT',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }

            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog(get_class($this)."::insert Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }

    /**
     *	Update the line object into db
     *
	 *	@param      int		$notrigger		1 = disable triggers
     *	@return		int		<0 si ko, >0 si ok
     */
    function update($notrigger=0)
    {
        global $conf,$langs,$user;

		$error=0;

        // Clean parameters
        if (empty($this->tva_tx)) $this->tva_tx=0;
        if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
        if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
        if (empty($this->qty)) $this->qty=0;
        if (empty($this->total_localtax1)) $this->total_localtax1=0;
        if (empty($this->total_localtax2)) $this->total_localtax2=0;
        if (empty($this->marque_tx)) $this->marque_tx=0;
        if (empty($this->marge_tx)) $this->marge_tx=0;
        if (empty($this->remise)) $this->remise=0;
        if (empty($this->remise_percent)) $this->remise_percent=0;
        if (empty($this->info_bits)) $this->info_bits=0;
        if (empty($this->special_code)) $this->special_code=0;
        if (empty($this->product_type)) $this->product_type=0;
        if (empty($this->fk_parent_line)) $this->fk_parent_line=0;
		if (empty($this->pa_ht)) $this->pa_ht=0;

		// si prix d'achat non renseigné et utilisé pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}

		$this->db->begin();

        // Mise a jour ligne en base
        $sql = "UPDATE ".MAIN_DB_PREFIX."repairdet SET";
        $sql.= " description='".$this->db->escape($this->desc)."'";
		$sql.= " , label=".(! empty($this->label)?"'".$this->db->escape($this->label)."'":"null");
        $sql.= " , tva_tx=".price2num($this->tva_tx);
        $sql.= " , localtax1_tx=".price2num($this->localtax1_tx);
        $sql.= " , localtax2_tx=".price2num($this->localtax2_tx);
        $sql.= " , qty=".price2num($this->qty);
        $sql.= " , subprice=".price2num($this->subprice)."";
        $sql.= " , remise_percent=".price2num($this->remise_percent)."";
        $sql.= " , price=".price2num($this->price)."";					// TODO A virer
        $sql.= " , remise=".price2num($this->remise)."";				// TODO A virer
        if (empty($this->skip_update_total))
        {
            $sql.= " , total_ht=".price2num($this->total_ht)."";
            $sql.= " , total_tva=".price2num($this->total_tva)."";
            $sql.= " , total_ttc=".price2num($this->total_ttc)."";
			$sql.= " , total_localtax1=".price2num($this->total_localtax1);
			$sql.= " , total_localtax2=".price2num($this->total_localtax2);
        }
		$sql.= " , fk_product_fournisseur_price=".(! empty($this->fk_fournprice)?$this->fk_fournprice:"null");
		$sql.= " , buy_price_ht='".price2num($this->pa_ht)."'";
        $sql.= " , info_bits=".$this->info_bits;
        $sql.= " , special_code=".$this->special_code;
		$sql.= " , date_start=".(! empty($this->date_start)?"'".$this->db->idate($this->date_start)."'":"null");
		$sql.= " , date_end=".(! empty($this->date_end)?"'".$this->db->idate($this->date_end)."'":"null");
        $sql.= " , product_type=".$this->product_type;
		$sql.= " , fk_parent_line=".(! empty($this->fk_parent_line)?$this->fk_parent_line:"null");
        if (! empty($this->rang)) $sql.= ", rang=".$this->rang;
        $sql.= " WHERE rowid = ".$this->rowid;

        dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (! $notrigger)
            {
                // Appel des triggers
                include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
                $interface=new Interfaces($this->db);
                $result = $interface->run_triggers('LINEORDER_UPDATE',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }

            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog(get_class($this)."::update Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }

    /**
     *	Update totals of order into database
     *
     *	@return		int		<0 if ko, >0 if ok
     */
    function update_total()
    {
        $this->db->begin();

        // Clean parameters
        if (empty($this->total_localtax1)) $this->total_localtax1=0;
        if (empty($this->total_localtax2)) $this->total_localtax2=0;

        // Mise a jour ligne en base
        $sql = "UPDATE ".MAIN_DB_PREFIX."repairdet SET";
        $sql.= " total_ht='".price2num($this->total_ht)."'";
        $sql.= ",total_tva='".price2num($this->total_tva)."'";
        $sql.= ",total_localtax1='".price2num($this->total_localtax1)."'";
        $sql.= ",total_localtax2='".price2num($this->total_localtax2)."'";
        $sql.= ",total_ttc='".price2num($this->total_ttc)."'";
        $sql.= " WHERE rowid = ".$this->rowid;

        dol_syslog("RepairLine::update_total sql=$sql");

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("RepairLine::update_total Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }
}

?>
