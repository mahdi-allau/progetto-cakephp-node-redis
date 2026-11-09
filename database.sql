-- Schema di esempio per la tabella messages (PostgreSQL)

CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    modified TIMESTAMP WITHOUT TIME ZONE
);
