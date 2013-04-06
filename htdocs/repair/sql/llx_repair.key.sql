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

ALTER TABLE llx_repair ADD UNIQUE INDEX uk_repair_ref (ref, entity);

ALTER TABLE llx_repair ADD INDEX idx_repair_fk_soc (fk_soc);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_user_author (fk_user_author);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_user_valid_e (fk_user_valid_e);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_user_reply_e (fk_user_reply_e);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_user_valid_r (fk_user_valid_r);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_user_cloture (fk_user_cloture);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_projet (fk_projet);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_account(fk_account);
ALTER TABLE llx_repair ADD INDEX idx_repair_fk_currency(fk_currency);

ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_soc			FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_machine		FOREIGN KEY (fk_machine) REFERENCES llx_machine (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_machine		FOREIGN KEY (fk_machine_lend) REFERENCES llx_machine (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_user_author	FOREIGN KEY (fk_user_author) REFERENCES llx_user (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_user_valid_e	FOREIGN KEY (fk_user_valid_e)  REFERENCES llx_user (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_user_reply_e	FOREIGN KEY (fk_user_reply_e)  REFERENCES llx_user (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_user_valid_r	FOREIGN KEY (fk_user_valid_r)  REFERENCES llx_user (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_user_cloture	FOREIGN KEY (fk_user_cloture) REFERENCES llx_user (rowid);
ALTER TABLE llx_repair ADD CONSTRAINT fk_repair_fk_projet		FOREIGN KEY (fk_projet) REFERENCES llx_projet (rowid);

