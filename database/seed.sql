-- ============================================================
-- LumaStay — Dados de demonstração
-- Executar depois de schema.sql
-- Reinicialização: voltar a executar schema.sql e este ficheiro.
-- ============================================================

USE lumastay;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Administração
-- Email: admin@lumastay.pt
-- Palavra-passe de demonstração: LumaStay2026!
-- ------------------------------------------------------------
INSERT INTO admin_users (name, email, password_hash) VALUES
('Equipa LumaStay', 'admin@lumastay.pt', '$2y$10$pOjGeKDDXgWY2CQ6GAsc8en99eSqTn3LxJO6QArT0NZbUgfEjGfRK');

-- ------------------------------------------------------------
-- Alojamentos
-- ------------------------------------------------------------
INSERT INTO accommodations
(name, slug, description, location, address, type, price_per_night, capacity, bedrooms, bathrooms, amenities, image_url, rating, review_count, active, featured) VALUES
(
 'Casa da Luz',
 'casa-da-luz',
 'Um apartamento luminoso no coração de Alfama, com janelas amplas sobre os telhados de Lisboa. A decoração combina madeira natural, linho e peças de artesãos portugueses. A poucos minutos a pé do Castelo de São Jorge e do miradouro de Santa Luzia.',
 'Lisboa',
 'Rua dos Remédios 42, Alfama, 1100-028 Lisboa',
 'apartamento', 185.00, 2, 1, 1,
 '["Wi-Fi de alta velocidade", "Ar condicionado", "Vista para a cidade", "Cozinha equipada", "Máquina de café", "Check-in autónomo"]',
 'assets/images/alojamentos/lisboa.svg', 4.9, 128, 1, 1
),
(
 'Refúgio do Douro',
 'refugio-do-douro',
 'Uma quinta privada entre socalcos de vinha, com vista sobre o rio Douro. A casa principal mantém paredes de pedra e vigas originais, agora acompanhadas por conforto contemporâneo. O pequeno-almoço é servido no terraço, com produtos da região.',
 'Douro',
 'Quinta das Oliveiras, Estrada do Pinhão 118, 5085-060 Pinhão',
 'quinta', 320.00, 6, 3, 2,
 '["Piscina privada", "Vista para o Douro", "Pequeno-almoço incluído", "Lareira", "Estacionamento gratuito", "Wi-Fi", "Cozinha equipada"]',
 'assets/images/alojamentos/douro.svg', 4.8, 96, 1, 1
),
(
 'Villa Atlântica',
 'villa-atlantica',
 'Uma villa de arquitetura contemporânea a poucos passos da Praia da Luz. Os espaços interiores abrem-se para um jardim mediterrânico e uma piscina aquecida virada a sul. Ideal para famílias ou grupos que procuram privacidade no Algarve.',
 'Algarve',
 'Rua da Falésia 7, Praia da Luz, 8600-117 Lagos',
 'villa', 410.00, 8, 4, 3,
 '["Piscina aquecida", "Vista para o mar", "Jardim privado", "Churrasqueira", "Ar condicionado", "Estacionamento gratuito", "Wi-Fi", "Máquina de lavar"]',
 'assets/images/alojamentos/algarve.svg', 4.9, 214, 1, 1
),
(
 'Casa do Bosque',
 'casa-do-bosque',
 'Escondida entre árvores centenárias da Serra de Sintra, esta casa oferece silêncio e luz filtrada. A lareira da sala e a cozinha de autor tornam-na acolhedora em qualquer estação. O centro histórico fica a dez minutos de carro.',
 'Sintra',
 'Caminho do Bosque 15, São Pedro de Penaferrim, 2710-511 Sintra',
 'casa', 245.00, 4, 2, 2,
 '["Lareira", "Jardim privado", "Pequeno-almoço incluído", "Wi-Fi", "Estacionamento gratuito", "Cozinha equipada", "Berço disponível"]',
 'assets/images/alojamentos/sintra.svg', 4.7, 87, 1, 1
),
(
 'Estúdio Ribeira',
 'estudio-ribeira',
 'Um estúdio compacto e cuidadosamente desenhado sobre as casas da Ribeira do Porto. A varanda estreita é o melhor lugar para ver o rio acordar. A localização permite explorar a cidade inteira a pé.',
 'Porto',
 'Rua da Lada 33, Ribeira, 4050-327 Porto',
 'apartamento', 145.00, 2, 1, 1,
 '["Vista para o rio", "Wi-Fi de alta velocidade", "Ar condicionado", "Kitchenette equipada", "Máquina de café", "Check-in autónomo"]',
 'assets/images/alojamentos/porto.svg', 4.6, 153, 1, 0
),
(
 'Quinta da Cal',
 'quinta-da-cal',
 'Uma quinta alentejana rodeada de laranjeiras e olivais, a poucos minutos de Évora. As paredes caiadas e os móveis de madeira criam uma atmosfera fresca e serena. A piscina exterior e o forno a lenha convidam a ficar.',
 'Évora',
 'Estrada da Cal 5, Nossa Senhora da Tourega, 7000-194 Évora',
 'quinta', 275.00, 6, 3, 2,
 '["Piscina exterior", "Pomar e olival", "Lareira", "Pequeno-almoço incluído", "Estacionamento gratuito", "Wi-Fi", "Churrasqueira"]',
 'assets/images/alojamentos/evora.svg', 4.8, 64, 1, 0
),
(
 'Suite Jardim do Mar',
 'suite-jardim-do-mar',
 'Uma suite virada para o Atlântico, na costa oeste da Madeira. A varanda permite acompanhar o movimento das ondas e o pôr do sol sobre o mar. O pequeno-almoço tropical é preparado diariamente com fruta da ilha.',
 'Madeira',
 'Caminho do Mar 21, Jardim do Mar, 9370-412 Calheta',
 'suite', 195.00, 2, 1, 1,
 '["Vista para o oceano", "Varanda privada", "Pequeno-almoço incluído", "Wi-Fi", "Ar condicionado", "Acesso à praia"]',
 'assets/images/alojamentos/madeira.svg', 4.9, 102, 1, 0
),
(
 'Loft Comporta',
 'loft-comporta',
 'Um loft de tons claros entre pinheiros e dunas, desenhado para desacelerar. O espaço aberto integra sala, cozinha e quarto, com uma piscina pequena no pátio exterior. A praia fica a uma caminhada curta através da reserva natural.',
 'Comporta',
 'Rua dos Pinheiros 9, Comporta, 7580-675 Alcácer do Sal',
 'loft', 230.00, 4, 2, 1,
 '["Piscina exterior", "Acesso à praia", "Wi-Fi", "Ar condicionado", "Estacionamento gratuito", "Cozinha equipada", "Pátio privado"]',
 'assets/images/alojamentos/comporta.svg', 4.7, 71, 1, 0
);

-- ------------------------------------------------------------
-- Hóspedes
-- ------------------------------------------------------------
INSERT INTO guests (full_name, email, phone) VALUES
('Mariana Costa', 'mariana.costa@exemplo.pt', '+351 912 345 678'),
('Tiago Almeida', 'tiago.almeida@exemplo.pt', '+351 934 567 890'),
('Inês Ferreira', 'ines.ferreira@exemplo.pt', '+351 961 234 567'),
('Rui Martins', 'rui.martins@exemplo.pt', '+351 927 890 123'),
('Sofia Rodrigues', 'sofia.rodrigues@exemplo.pt', '+351 913 456 789'),
('Pedro Nunes', 'pedro.nunes@exemplo.pt', '+351 968 123 456'),
('Beatriz Silva', 'beatriz.silva@exemplo.pt', '+351 926 345 678'),
('André Lopes', 'andre.lopes@exemplo.pt', '+351 935 678 901'),
('Carolina Matos', 'carolina.matos@exemplo.pt', '+351 917 234 567'),
('Miguel Santos', 'miguel.santos@exemplo.pt', '+351 962 789 012');

-- ------------------------------------------------------------
-- Reservas (16, com vários estados e datas)
-- ------------------------------------------------------------
INSERT INTO reservations
(reservation_code, accommodation_id, guest_id, check_in, check_out, guests_count, price_per_night, total_price, status, created_at) VALUES
('LS-7K2M9A', 1, 1, '2026-10-10', '2026-10-13', 2, 185.00, 555.00, 'concluida', NOW() - INTERVAL 40 DAY),
('LS-4P8R2T', 2, 2, '2026-11-20', '2026-11-23', 4, 320.00, 960.00, 'pendente', NOW() - INTERVAL 6 DAY),
('LS-9D3F6L', 3, 3, '2026-12-20', '2026-12-27', 8, 410.00, 2870.00, 'confirmada', NOW() - INTERVAL 18 DAY),
('LS-2H7N5C', 4, 4, '2026-11-12', '2026-11-16', 4, 245.00, 980.00, 'confirmada', NOW() - INTERVAL 12 DAY),
('LS-6B1Q8V', 5, 5, '2026-11-15', '2026-11-18', 2, 145.00, 435.00, 'cancelada', NOW() - INTERVAL 20 DAY),
('LS-3J9W4E', 5, 6, '2026-12-04', '2026-12-07', 2, 145.00, 435.00, 'pendente', NOW() - INTERVAL 4 DAY),
('LS-8S2Y7K', 6, 7, '2026-11-27', '2026-11-30', 5, 275.00, 825.00, 'confirmada', NOW() - INTERVAL 9 DAY),
('LS-5M4C9X', 7, 8, '2027-01-05', '2027-01-10', 2, 195.00, 975.00, 'confirmada', NOW() - INTERVAL 15 DAY),
('LS-1T6G3R', 8, 9, '2026-12-28', '2027-01-02', 4, 230.00, 1150.00, 'pendente', NOW() - INTERVAL 2 DAY),
('LS-7V8B2D', 1, 10, '2026-09-01', '2026-09-04', 2, 185.00, 555.00, 'concluida', NOW() - INTERVAL 70 DAY),
('LS-0L5Z6N', 2, 1, '2027-02-14', '2027-02-17', 2, 320.00, 960.00, 'confirmada', NOW() - INTERVAL 8 DAY),
('LS-4R9A1P', 3, 4, '2026-11-08', '2026-11-11', 6, 410.00, 1230.00, 'cancelada', NOW() - INTERVAL 25 DAY),
('LS-6C3E8M', 4, 2, '2027-01-15', '2027-01-18', 2, 245.00, 735.00, 'pendente', NOW() - INTERVAL 3 DAY),
('LS-2W7H4J', 6, 3, '2026-08-10', '2026-08-15', 6, 275.00, 1375.00, 'concluida', NOW() - INTERVAL 90 DAY),
('LS-9N1S5B', 7, 5, '2027-03-01', '2027-03-05', 2, 195.00, 780.00, 'confirmada', NOW() - INTERVAL 11 DAY),
('LS-3Q6T0F', 8, 6, '2026-11-21', '2026-11-24', 2, 230.00, 690.00, 'confirmada', NOW() - INTERVAL 7 DAY);
