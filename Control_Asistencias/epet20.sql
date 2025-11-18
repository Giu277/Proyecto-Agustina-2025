-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-11-2025 a las 13:02:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `epet20`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asiste-c`
--

CREATE TABLE `asiste-c` (
  `Id_asiste` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `Entrada` time(6) NOT NULL,
  `Salida` time(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asiste-c`
--

INSERT INTO `asiste-c` (`Id_asiste`, `fecha`, `Entrada`, `Salida`) VALUES
(555, '2025-11-17', '13:42:42.000000', '13:42:42.000000'),
(1312, '2025-11-17', '18:21:18.000000', '18:21:18.000000'),
(2134, '2025-11-17', '14:23:46.000000', '14:23:46.000000'),
(3245, '2025-11-17', '14:38:37.000000', '14:38:37.000000'),
(3333, '2025-11-17', '16:50:34.000000', '16:50:34.000000'),
(3698, '2025-11-18', '12:34:53.000000', '12:34:53.000000'),
(9874, '2025-11-17', '14:46:43.000000', '14:46:43.000000'),
(45682, '2025-11-17', '14:51:05.000000', '14:51:05.000000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargo`
--

CREATE TABLE `cargo` (
  `id_cargo` int(11) NOT NULL,
  `Denominacion` varchar(50) NOT NULL,
  `Entrada` time(6) NOT NULL,
  `Salida` time(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cargo`
--

INSERT INTO `cargo` (`id_cargo`, `Denominacion`, `Entrada`, `Salida`) VALUES
(1, 'Portero', '00:00:00.000000', '00:00:00.000000'),
(2, 'Profesor', '00:00:00.000000', '00:00:00.000000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso`
--

CREATE TABLE `curso` (
  `Id_curso` int(11) NOT NULL,
  `División` varchar(50) NOT NULL,
  `anio` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horario`
--

CREATE TABLE `horario` (
  `Id_horario` int(11) NOT NULL,
  `Dia` date NOT NULL,
  `Entrada` time(6) NOT NULL,
  `Salida` time(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horario`
--

INSERT INTO `horario` (`Id_horario`, `Dia`, `Entrada`, `Salida`) VALUES
(1, '0000-00-00', '00:00:00.000000', '08:00:00.000000'),
(2, '0000-00-00', '08:00:00.000000', '12:00:00.000000'),
(3, '0000-00-00', '05:00:00.000000', '19:00:00.000000'),
(4, '0000-00-00', '00:00:00.000000', '04:00:00.000000'),
(6, '0000-00-00', '00:00:00.000000', '23:00:00.000000'),
(7, '0000-00-00', '08:00:00.000000', '11:20:00.000000'),
(8, '0000-00-00', '05:00:00.000000', '09:00:00.000000'),
(9, '0000-00-00', '08:00:00.000000', '10:00:00.000000'),
(10, '0000-00-00', '08:00:00.000000', '17:00:00.000000'),
(11, '0000-00-00', '08:00:00.000000', '13:00:00.000000'),
(12, '0000-00-00', '07:00:00.000000', '13:00:00.000000'),
(13, '0000-00-00', '08:00:00.000000', '11:00:00.000000'),
(14, '0000-00-00', '08:00:00.000000', '10:00:00.000000'),
(15, '0000-00-00', '08:00:00.000000', '11:00:00.000000'),
(16, '0000-00-00', '05:00:00.000000', '09:00:00.000000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia`
--

CREATE TABLE `materia` (
  `Id_materia` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `Legajo` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Apellido` varchar(50) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `id_cargo` int(11) DEFAULT NULL,
  `contrasenia` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`Legajo`, `Nombre`, `Apellido`, `contraseña`, `id_cargo`, `contrasenia`) VALUES
(555, 'santino', 'reynoso', '', 2, '$2y$10$FROjujoZMQFw5Uo2DjRkaeWb6rrrCh4eJX/0ttDp2JVX8MtTGkwKy'),
(2134, 'sant', 'rey', '', 1, '$2y$10$qwHl0MJOKg.Lm3meTxyfteWrGPi6myHe2oHqfzkIVzqaUSi3JLlq6'),
(3421, 'santino', 'reynoso', '', 2, '$2y$10$xRcOIzeng0/uMTzR5FodUuoHmYoEQc9FH3RyafXJrKZcdIMCHncVK'),
(3698, 'Felix', 'San Martin', '', 1, '$2y$10$Ga7s7bquk4zZvG3qnba/m.JNTgVjh68dTzBH/2pn6L97NNCAfHMg.');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asiste-c`
--
ALTER TABLE `asiste-c`
  ADD PRIMARY KEY (`Id_asiste`);

--
-- Indices de la tabla `cargo`
--
ALTER TABLE `cargo`
  ADD PRIMARY KEY (`id_cargo`);

--
-- Indices de la tabla `curso`
--
ALTER TABLE `curso`
  ADD PRIMARY KEY (`Id_curso`);

--
-- Indices de la tabla `horario`
--
ALTER TABLE `horario`
  ADD PRIMARY KEY (`Id_horario`);

--
-- Indices de la tabla `materia`
--
ALTER TABLE `materia`
  ADD PRIMARY KEY (`Id_materia`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`Legajo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asiste-c`
--
ALTER TABLE `asiste-c`
  MODIFY `Id_asiste` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45683;

--
-- AUTO_INCREMENT de la tabla `cargo`
--
ALTER TABLE `cargo`
  MODIFY `id_cargo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `curso`
--
ALTER TABLE `curso`
  MODIFY `Id_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horario`
--
ALTER TABLE `horario`
  MODIFY `Id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `materia`
--
ALTER TABLE `materia`
  MODIFY `Id_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `Legajo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1234567892;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
