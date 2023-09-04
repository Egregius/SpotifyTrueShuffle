CREATE TABLE `history` (
  `pk` int(11) NOT NULL,
  `id` varchar(50) NOT NULL,
  `artist` varchar(150) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `stamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `history`
  ADD PRIMARY KEY (`pk`),
  ADD UNIQUE KEY `id` (`id`,`stamp`);


ALTER TABLE `history`
  MODIFY `pk` int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE `history_counts` (
  `id` varchar(50) NOT NULL,
  `artist` varchar(150) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `count` smallint(6) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `history_counts` 
  ADD PRIMARY KEY (`id`);

CREATE TABLE `played` (
  `id` varchar(50) NOT NULL,
  `stamp` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


ALTER TABLE `played`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stamp` (`stamp`);

CREATE TABLE `token` (
  `refreshToken` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
