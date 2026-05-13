USE gmao_onda;

INSERT INTO users (nom, prenom, email, password_hash, role, service)
VALUES
  ('Administrateur', '', 'admin@gmao-onda.local', 'Admin123!', 'admin', 'ESU'),
  ('TIJANI', 'Tarik', 'tarik@gmao-onda.local', 'Tech123!', 'technicien', 'ESU'),
  ('Responsable', 'Maintenance', 'responsable@gmao-onda.local', 'Resp123!', 'responsable', 'ESU'),
  ('Superviseur', 'Exploitation', 'superviseur@gmao-onda.local', 'Sup123!', 'superviseur', 'ESU'),
  ('Agent', 'Exploitation', 'agent@gmao-onda.local', 'Agent123!', 'agent_exploitation', 'ESU')
ON DUPLICATE KEY UPDATE
  nom = VALUES(nom),
  prenom = VALUES(prenom),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  service = VALUES(service);
