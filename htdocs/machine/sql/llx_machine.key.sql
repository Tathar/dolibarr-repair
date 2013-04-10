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

ALTER TABLE llx_machine ADD INDEX idx_machine_serial_num (serial_num);
ALTER TABLE llx_machine ADD INDEX idx_machine_short_ref (short_ref)

ALTER TABLE llx_machine ADD CONSTRAINT fk_machine_fk_machine_mod FOREIGN KEY (fk_model) REFERENCES llx_machine_model (rowid);
