CREATE TABLE nodes(
    id INTEGER PRIMARY KEY,
    name VARCHAR(250) NOT NULL,
    year INTEGER CHECK (year > 2000 AND year < 2100),
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    sex ENUM('F', 'M', 'NB', 'U', 'NH'),
    UNIQUE (name, year),
    UNIQUE (x, y)
);

CREATE TABLE edges(
    id INTEGER UNIQUE AUTO_INCREMENT,
    a INTEGER REFERENCES nodes(id),
    b INTEGER REFERENCES nodes(id),
    votes INTEGER DEFAULT 0,
    PRIMARY KEY (a, b),
    CHECK (a < b)
);