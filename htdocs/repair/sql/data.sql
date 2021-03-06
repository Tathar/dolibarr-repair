-- Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
-- Copyright (C) 2003      Jean-Louis Bergamo   <jlb@j1b.org>
-- Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
-- Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
-- Copyright (C) 2004      Guillaume Delecourt  <guillaume.delecourt@opensides.be>
-- Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
-- Copyright (C) 2007 	   Patrick Raguin       <patrick.raguin@gmail.com>
-- Copyright (C) 2013      Pierre-Emmanuel DOUET	<tathar.dev@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
--

--
-- Ne pas placer de commentaire en fin de ligne, ce fichier est parsé lors
-- de l'install et tous les sigles '--' sont supprimés.
--

--
-- Les types de contact d'un element
--
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (12501, 'repair','internal', 'SALESREPFOLL',  'Responsable suivi de la réparation', 1);
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (12502,'repair','external', 'BILLING',       'Contact client facturation réparation', 1);
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (12503,'repair','external', 'CUSTOMER',      'Contact client suivi réparation', 1);
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (12504,'repair','external', 'SHIPPING',      'Contact client livraison réparation', 1);
--
--
--
insert into llx_c_repair_support(rowid,code, label, active, module ) values (1,'Mag','Magasin', 1, 'repair');




