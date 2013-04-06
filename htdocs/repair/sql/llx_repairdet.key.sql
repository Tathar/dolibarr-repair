-- ===================================================================
-- Copyright (C) 2006 Laurent Destailleur  <eldy@users.sourceforge.net>
-- Copyright (C) 2011 Regis Houssin        <regis@dolibarr.fr>
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



ALTER TABLE llx_repairdet ADD INDEX idx_repairdet_fk_repair (fk_repair);
ALTER TABLE llx_repairdet ADD INDEX idx_repairdet_fk_product (fk_product);

ALTER TABLE llx_repairdet ADD CONSTRAINT fk_repairdet_fk_repair FOREIGN KEY (fk_repair) REFERENCES llx_repair (rowid);
