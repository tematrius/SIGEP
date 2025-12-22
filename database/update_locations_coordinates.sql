-- Ajout des coordonnées géographiques pour les provinces de la RDC
-- Ces coordonnées représentent approximativement le centre de chaque province

-- Ajouter les colonnes latitude et longitude si elles n'existent pas
ALTER TABLE locations 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) COMMENT 'Latitude GPS',
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) COMMENT 'Longitude GPS';

-- Mettre à jour les coordonnées des provinces
UPDATE locations SET latitude = -4.3217, longitude = 15.3125, province = 'KS' WHERE code = 'KS'; -- Kinshasa
UPDATE locations SET latitude = -4.4419, longitude = 15.2663, province = 'BC' WHERE code = 'BC'; -- Bas-Congo (Kongo Central)
UPDATE locations SET latitude = -5.0332, longitude = 18.7369, province = 'KW' WHERE code = 'KW'; -- Kwilu
UPDATE locations SET latitude = -3.3108, longitude = 17.1419, province = 'KG' WHERE code = 'KG'; -- Kwango
UPDATE locations SET latitude = -5.8906, longitude = 22.4514, province = 'KS2' WHERE code = 'KS2'; -- Kasaï
UPDATE locations SET latitude = -6.1375, longitude = 23.5981, province = 'KO' WHERE code = 'KO'; -- Kasaï-Oriental
UPDATE locations SET latitude = -5.5592, longitude = 20.7480, province = 'KC' WHERE code = 'KC'; -- Kasaï-Central
UPDATE locations SET latitude = -11.6650, longitude = 27.4794, province = 'KT' WHERE code = 'KT'; -- Katanga (Haut-Katanga)
UPDATE locations SET latitude = -7.4667, longitude = 20.7833, province = 'LS' WHERE code = 'LS'; -- Lomami
UPDATE locations SET latitude = -8.8333, longitude = 25.6833, province = 'LU' WHERE code = 'LU'; -- Lualaba
UPDATE locations SET latitude = -9.2000, longitude = 23.6000, province = 'HK' WHERE code = 'HK'; -- Haut-Katanga
UPDATE locations SET latitude = -10.7167, longitude = 25.4667, province = 'HL' WHERE code = 'HL'; -- Haut-Lomami
UPDATE locations SET latitude = -6.3642, longitude = 21.1083, province = 'SA' WHERE code = 'SA'; -- Sankuru
UPDATE locations SET latitude = -4.3733, longitude = 19.5564, province = 'MA' WHERE code = 'MA'; -- Maï-Ndombe
UPDATE locations SET latitude = -1.8312, longitude = 21.0936, province = 'EQ' WHERE code = 'EQ'; -- Équateur
UPDATE locations SET latitude = -2.7000, longitude = 23.6000, province = 'TS' WHERE code = 'TS'; -- Tshopo
UPDATE locations SET latitude = 0.7681, longitude = 18.2764, province = 'SU' WHERE code = 'SU'; -- Sud-Ubangi
UPDATE locations SET latitude = 2.4594, longitude = 18.5625, province = 'NU' WHERE code = 'NU'; -- Nord-Ubangi
UPDATE locations SET latitude = 1.6308, longitude = 20.9200, province = 'MO' WHERE code = 'MO'; -- Mongala
UPDATE locations SET latitude = 0.5192, longitude = 25.1900, province = 'BU' WHERE code = 'BU'; -- Bas-Uélé
UPDATE locations SET latitude = 2.1100, longitude = 27.4833, province = 'HU' WHERE code = 'HU'; -- Haut-Uélé
UPDATE locations SET latitude = 0.0000, longitude = 29.0000, province = 'IT' WHERE code = 'IT'; -- Ituri
UPDATE locations SET latitude = -2.5075, longitude = 28.8617, province = 'NK' WHERE code = 'NK'; -- Nord-Kivu
UPDATE locations SET latitude = -2.2167, longitude = 28.4667, province = 'SK' WHERE code = 'SK'; -- Sud-Kivu
UPDATE locations SET latitude = -3.3731, longitude = 29.3589, province = 'MN' WHERE code = 'MN'; -- Maniema
UPDATE locations SET latitude = -11.2044, longitude = 27.4833, province = 'TG' WHERE code = 'TG'; -- Tanganyika

-- Ajouter un index sur les colonnes de géolocalisation
CREATE INDEX IF NOT EXISTS idx_lat_long ON locations(latitude, longitude);

-- Commentaires
ALTER TABLE locations MODIFY COLUMN latitude DECIMAL(10, 8) COMMENT 'Latitude GPS du centre de la province';
ALTER TABLE locations MODIFY COLUMN longitude DECIMAL(11, 8) COMMENT 'Longitude GPS du centre de la province';
