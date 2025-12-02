-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-12-2025 a las 11:01:43
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
(1010, '2025-12-02', '06:42:38.000000', '06:50:01.000000'),
(1324, '2025-12-02', '06:29:40.000000', '06:58:34.000000'),
(2134, '2025-11-27', '08:38:04.000000', '00:00:00.000000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargo`
--

CREATE TABLE `cargo` (
  `id_cargo` int(11) NOT NULL,
  `Denominacion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cargo`
--

INSERT INTO `cargo` (`id_cargo`, `Denominacion`) VALUES
(1, 'Portero'),
(2, 'Profesor'),
(3, 'Preceptor'),
(4, 'Director'),
(6, 'otros'),
(7, 'otros'),
(8, 'otros');

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
  `Salida` time(6) NOT NULL,
  `id_cargo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horario`
--

INSERT INTO `horario` (`Id_horario`, `Dia`, `Entrada`, `Salida`, `id_cargo`) VALUES
(1, '0000-00-00', '00:00:00.000000', '08:00:00.000000', NULL),
(2, '0000-00-00', '08:00:00.000000', '12:00:00.000000', NULL),
(3, '0000-00-00', '05:00:00.000000', '19:00:00.000000', NULL),
(4, '0000-00-00', '00:00:00.000000', '04:00:00.000000', NULL),
(6, '0000-00-00', '00:00:00.000000', '23:00:00.000000', NULL),
(7, '0000-00-00', '08:00:00.000000', '11:20:00.000000', NULL),
(8, '0000-00-00', '05:00:00.000000', '09:00:00.000000', NULL),
(9, '0000-00-00', '08:00:00.000000', '10:00:00.000000', NULL),
(10, '0000-00-00', '08:00:00.000000', '17:00:00.000000', NULL),
(11, '0000-00-00', '08:00:00.000000', '13:00:00.000000', NULL),
(12, '0000-00-00', '07:00:00.000000', '13:00:00.000000', NULL),
(13, '0000-00-00', '08:00:00.000000', '11:00:00.000000', NULL),
(14, '0000-00-00', '08:00:00.000000', '10:00:00.000000', NULL),
(15, '0000-00-00', '08:00:00.000000', '11:00:00.000000', NULL),
(16, '0000-00-00', '05:00:00.000000', '09:00:00.000000', NULL),
(17, '0000-00-00', '09:30:00.000000', '15:30:00.000000', NULL),
(18, '0000-00-00', '09:00:00.000000', '15:30:00.000000', 3);

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
(1010, 'franco', 'messi', '', 3, '$2y$10$fEOKiCWD5iacAY4P6IN2uOzgMTGB0RkCXwfg8Ut2W5wE/dH/yVsam'),
(1324, 'san', 're', '', 3, '$2y$10$5Yzrig2FhriPW9.O17X2u.LtD8jULdBeVBE8GWdby47WFbg1Lkij2'),
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
  MODIFY `id_cargo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `curso`
--
ALTER TABLE `curso`
  MODIFY `Id_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horario`
--
ALTER TABLE `horario`
  MODIFY `Id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
