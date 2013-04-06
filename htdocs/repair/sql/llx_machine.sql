-- ===================================================================
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
-- ===================================================================

create table llx_machine
(
  rowid					integer AUTO_INCREMENT PRIMARY KEY,
  fk_model				integer	NOT NULL,				-- Ref Modele
  serial_num			varchar(255),					-- Numero de serie (optionnel)
  short_ref				varchar(255),					-- Ref rapide (exemple, pour un véhicule, plaque d'immatriculation)

  tms					timestamp
  
)ENGINE=innodb;
