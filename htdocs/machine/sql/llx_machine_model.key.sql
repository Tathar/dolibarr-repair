-- ============================================================================
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
-- ============================================================================


-- Supprimme orphelins pour permettre montee de la cle
-- V4 DELETE llx_repair FROM llx_repair LEFT JOIN llx_societe ON llx_repair.fk_soc = llx_societe.rowid WHERE llx_societe.rowid IS NULL; 

ALTER TABLE llx_machine_type ADD INDEX idx_machine_model_type_id (type_id);
ALTER TABLE llx_machine_model ADD INDEX idx_machine_model_model (model);
ALTER TABLE llx_machine_model ADD INDEX idx_machine_model_n_model (n_model);


ALTER TABLE llx_machine_model ADD CONSTRAINT fk_machine_mod_fk_machine_tra FOREIGN KEY (fk_trademark) REFERENCES llx_machine_trademark (rowid);
