<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *  \file       dev/skeletons/machinemodel.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2013-03-07 21:04
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");

require_once(DOL_DOCUMENT_ROOT."/repair/class/machine_trademark.class.php");

/**
 *	Put here description of your class
 */
class Machine_model // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='machinemodel';			//!< Id that identify managed objects
	//var $table_element='machinemodel';	//!< Name of table without prefix where object is stored

    var $id;
    
//	var $fk_trademark;

	var $trademark;
	var $type_id;
	var $model;
	var $n_model;
//	var $tms='';

    


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *  Create object into database
     *
     *  @param	User	$user        User that create
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->trademark)) $this->trademark=trim($this->trademark);
        else 
		{
		dol_syslog(get_class($this)."::create trademark is Null", LOG_ERR);
		return -1;
		}

//		if (isset($this->fk_trademark)) $this->fk_trademark=trim($this->fk_trademark);
		if (isset($this->type_id)) $this->type=trim_id($this->type_id);
		if (isset($this->model)) $this->model=trim($this->model);
		if (isset($this->n_model)) $this->n_model=trim($this->n_model);

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request

//<Tathar>
		dol_syslog(get_class($this)."::create //<Tathar>", LOG_DEBUG);
		$obj_trademark = new Machine_trademark($this->db);
		$obj_trademark->trademark = $this->trademark;
		$fk_trademark = $obj_trademark->create($user, $notrigger);

		$sql = "SELECT";				//on test si le modele existe deja;
		$sql.= " rowid";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."machine_model";
        $sql.= " WHERE fk_trademark = ".$fk_trademark." AND";
		$sql.= " model = '".$this->model."'";
		$sql.= " AND n_model = "."'".$this->n_model."'";

    	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)) //le modele existe
            {
                $obj = $this->db->fetch_object($resql);
                $this->id = $obj->rowid; 
				$this->db->free($resql);
	            return $this->id;			//le modele existe, on le retourn        
            }
            
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
            return -1;
        }
		dol_syslog(get_class($this)."::create //</Tathar>", LOG_DEBUG);
//</Tathar>									//le modele n'existe pas, on le crée
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."machine_model(";		
		
		$sql.= "fk_trademark,";
		$sql.= "type_id,";
		$sql.= "model,";
		$sql.= "n_model";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($fk_trademark)?'NULL':"'".$fk_trademark."'").",";
		$sql.= " ".(! isset($this->type_id)?'NULL':"'".$this->db->escape($this->type_id)."'").",";
		$sql.= " ".(! isset($this->model)?'NULL':"'".$this->db->escape($this->model)."'").",";
		$sql.= " ".(! isset($this->n_model)?'NULL':"'".$this->db->escape($this->n_model)."'");

		$sql.= ")";
		dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
		$this->db->begin();

        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."machine_model");
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *  Load object in memory from database
     *
     *  @param	int		$id    Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    function fetch($id)
    {

		global $langs;
        $sql = "SELECT";
		$sql.= " m.rowid,";
		
//		$sql.= " m.fk_trademark,";
		$sql.= " m.type_id,";
		$sql.= " m.model,";
		$sql.= " m.n_model,";
		$sql.= " m.tms";

		$sql.= " t.trademark";

		
        $sql.= " FROM ".MAIN_DB_PREFIX."machine_model as m";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_trademark as t";
		$sql.= " ON m.fk_trademark = t.rowid";
        $sql.= " WHERE m.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->trademark = $obj->trademark;
				$this->type_id = $obj->type_id;
				$this->model = $obj->model;
				$this->n_model = $obj->n_model;
				$this->tms = $this->db->jdate($obj->tms);

                
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modify
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->trademark)) $this->trademark=trim($this->trademark);
		if (isset($this->type_id)) $this->type_id=trim($this->type_id);
		if (isset($this->model)) $this->model=trim($this->model);
		if (isset($this->n_model)) $this->n_model=trim($this->n_model);

        

		// Check parameters
		// Put here code to add control on parameters values
		$fk_trademark = $this->trademarkID($trademark);
		
        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."machine_model SET";
        
		$sql.= " fk_trademark=".(isset($fk_trademark)?$this->fk_trademark:"").",";
		$sql.= " type_id=".(isset($this->type_id)?"'".$this->db->escape($this->type_id)."'":"").",";
		$sql.= " model=".(isset($this->model)?"'".$this->db->escape($this->model)."'":"").",";
		$sql.= " n_model=".(isset($this->n_model)?"'".$this->db->escape($this->n_model)."'":"");
//		$sql.= " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null');

        
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			$this->clean($user, $notrigger);
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param  User	$user        User that delete
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."machine_model";
    		$sql.= " WHERE rowid=".$this->id;

    		dol_syslog(get_class($this)."::delete sql=".$sql);
    		$resql = $this->db->query($sql);
        	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			$objtred = new Machine_trademark($this->db);
			$objtred->clean($user, $notrigger);
			return 1;
		}
	}



	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
/*	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Machine_model($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
//		$object->statut=0;

		// Clear fields
		// ...
//		$object->tms='';

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{


		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}
*/

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function initAsSpecimen()
	{
		$this->id=0;
		
		$this->trademark='Brother';
		$this->type_id='FAX';
		$this->model='FAX 8360P';
		$this->n_model='N/A';
		$this->tms='';

		
	}

//Tathar

	/**
	 *	Get trademark rowid
	 *	
	 *	@return	rowid or 0 if not exist
	 */
	function trademarkID($var)
	{
		if (! isset($var)) 
		{
			dol_syslog(get_class($this)."::trademarkID var is null", LOG_ERR);
	   		return -1;
		}
 
		$sql = "SELECT rowid";									//on verifie si la "marque" existe
		$sql.= " FROM ".MAIN_DB_PREFIX."machine_trademark";
		$sql.= " WHERE trademark = ".$var; 
//		$this->db->begin();
		dol_syslog(get_class($this)."::trademarkID sql=".$sql, LOG_DEBUG);
		$result=$this->db->query($sql);
    	if (! $result) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		if ( $result)
        {
			if ($this->db->num_rows($result))	//la "Marque" existe
            {
				$obj = $this->db->fetch_object($result);
                $fk_trademark = $obj->rowid;
			}
			else								//la "Marque" n'existe pas
			{
				$fk_trademark = 0;
			}
			$this->db->free($result);
			return $fk_trademark;
		}
		else
        {
            dol_print_error($this->db);
			return -1;
        }
	}


	/**
	 *	Create trademark and return rowid
	 *	
	 *	@return	rowid or 0 if not exist
	 */
	function createTrademark($var)
	{
		if (! isset($var)) 
		{
			dol_syslog(get_class($this)."::createTrademark var is null", LOG_ERR);
	   		return -1;
		}
 
		$sql = 'SELECT rowid';									//on verifie si la "marque" existe deja
		$sql.= ' FROM '.MAIN_DB_PREFIX.'machine_trademark';
		$sql.= " WHERE trademark='".$var."'"; 
//		$this->db->begin();
		dol_syslog(get_class($this)."::createTrademark sql=".$sql, LOG_DEBUG);
		$result = $this->db->query($sql);
    	if (! $result) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		if ( $result)
        {
			if ($this->db->num_rows($result))	//la "Marque" existe deja
            {
				$obj = $this->db->fetch_object($result);
                $fk_trademark = $obj->rowid;
			}
			else								//la "Marque" n'existe pas, on l'ajoute a la table
			{
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."machine_trademark(";
				$sql.= "trademark";
       			$sql.= ") VALUES (";
				$sql.= " "."'".$var."'";
				$sql.= ")";

//				$this->db->begin();

			   	dol_syslog(get_class($this)."::createTrademark sql=".$sql, LOG_DEBUG);
        		$resql=$this->db->query($sql);
    			if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
				if ($error)
				{
					foreach($this->errors as $errmsg)
					{
	    		        dol_syslog(get_class($this)."::createTrademark ".$errmsg, LOG_ERR);
	    		        $this->error.=($this->error?', '.$errmsg:$errmsg);
					}
					$this->db->rollback();
					return -1*$error;
				}
				else
				{
					$fk_trademark = $this->db->last_insert_id(MAIN_DB_PREFIX.'machine_trademark');
					$this->db->commit();
				}
			}

			$this->db->free($result);
			return $fk_trademark;
		}
		else
        {
            dol_print_error($this->db);
			return -1;
        }
	}


	/**
	 *  Clean model database
	 *
     *	@param  User	$user        User that delete
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, number of delete line
	 */
	function clean($user, $notrigger=0)
	{
//TODO

		$sql = "SELECT mm.rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."machine_model AS mm";
		$sql.= " WHERE mm.rowid";
		$sql.= " NOT IN (";
    	$sql.= " SELECT m.fk_model";
		$sql.= " FROM llx_machine AS m";
//		$sql.= " LEFT JOIN llx_machine_model AS mm ON m.fk_model = mm.rowid";
 		$sql.= " )";

//		$this->db->begin();
		dol_syslog(get_class($this)."::cleanModel sql=".$sql, LOG_DEBUG);
		$result=$this->db->query($sql);
		if ( $result)
        { 
			$num = $this->db->num_rows($result);
			$id = $this->id;
			dol_syslog(get_class($this)."::cleanModel num_rows = $num" , LOG_DEBUG);
			while ($obj=$this->db->fetch_object($resql))
            {
				$this->id = $obj->rowid;
				dol_syslog(get_class($this)."::cleanModel delete row = $this->id" , LOG_DEBUG);
				$this->delete( $user, $notrigger);
            }
			return $num;
		}
		else
        {
            dol_print_error($this->db);
            $this->error=$this->db->error();
            return -1;
        }
	}

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function setType_id( $id, $type_id )
	{
		
		       // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."machine_model SET";
        
		$sql.= ' type_id='."'".$type_id."'";

        $sql.= " WHERE rowid=".$id;

		$this->db->begin();

		dol_syslog(get_class($this)."::setType_id sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror();}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::setType_id ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}

		
	}

}
?>
