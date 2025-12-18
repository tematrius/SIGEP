-- Insertion des 26 provinces de la République Démocratique du Congo
-- Date: 17/12/2025

-- Supprimer les anciennes données et insérer les provinces
DELETE FROM locations;

-- Insertion des 26 provinces
INSERT INTO locations (name, type) VALUES
('Kinshasa', 'province'),
('Kongo Central', 'province'),
('Kwango', 'province'),
('Kwilu', 'province'),
('Mai-Ndombe', 'province'),
('Kasaï', 'province'),
('Kasaï-Central', 'province'),
('Kasaï-Oriental', 'province'),
('Lomami', 'province'),
('Sankuru', 'province'),
('Maniema', 'province'),
('Sud-Kivu', 'province'),
('Nord-Kivu', 'province'),
('Ituri', 'province'),
('Haut-Uélé', 'province'),
('Tshopo', 'province'),
('Bas-Uélé', 'province'),
('Nord-Ubangi', 'province'),
('Mongala', 'province'),
('Sud-Ubangi', 'province'),
('Équateur', 'province'),
('Tshuapa', 'province'),
('Tanganyika', 'province'),
('Haut-Lomami', 'province'),
('Lualaba', 'province'),
('Haut-Katanga', 'province');
