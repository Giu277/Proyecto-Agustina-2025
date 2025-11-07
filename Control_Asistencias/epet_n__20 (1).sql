-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-11-2025 a las 10:51:26
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
-- Base de datos: `epet n°20`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asiste`
--

CREATE TABLE `asiste` (
  `Id_asiste` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `Entrada` time(6) NOT NULL,
  `Salida` time(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Portero', '00:00:00.000000', '00:00:00.000000');

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
  `Apellido` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asiste`
--
ALTER TABLE `asiste`
  ADD PRIMARY KEY (`Id_asiste`);

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
-- AUTO_INCREMENT de la tabla `asiste`
--
ALTER TABLE `asiste`
  MODIFY `Id_asiste` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asiste-c`
--
ALTER TABLE `asiste-c`
  MODIFY `Id_asiste` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cargo`
--
ALTER TABLE `cargo`
  MODIFY `id_cargo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `curso`
--
ALTER TABLE `curso`
  MODIFY `Id_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horario`
--
ALTER TABLE `horario`
  MODIFY `Id_horario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materia`
--
ALTER TABLE `materia`
  MODIFY `Id_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `Legajo` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asiste`
--
ALTER TABLE `asiste`
  ADD CONSTRAINT `asiste_ibfk_1` FOREIGN KEY (`Id_asiste`) REFERENCES `materia` (`Id_materia`),
  ADD CONSTRAINT `asiste_ibfk_2` FOREIGN KEY (`Id_asiste`) REFERENCES `usuario` (`Legajo`);

--
-- Filtros para la tabla `asiste-c`
--
ALTER TABLE `asiste-c`
  ADD CONSTRAINT `asiste-c_ibfk_1` FOREIGN KEY (`Id_asiste`) REFERENCES `usuario` (`Legajo`),
  ADD CONSTRAINT `asiste-c_ibfk_2` FOREIGN KEY (`Id_asiste`) REFERENCES `cargo` (`id_cargo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
