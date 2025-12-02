<?php

include_once __DIR__ . '/../modelo/pais.php';
include_once __DIR__ . '/../modelo/departamento.php';
include_once __DIR__ . '/../modelo/municipio.php';
include_once __DIR__ . '/../modelo/barrio.php';

class GeoinfoController
{
    // --- PAISES ---
    public function listarPaises()
    {
        return PaisModel::listar();
    }

    public function crearPais($data)
    {
        if (empty($data['nombre'])) {
            throw new Exception("El nombre del país es obligatorio.", 400);
        }
        $id = PaisModel::crear($data['nombre'], $data['codigo_iso'] ?? null);
        return ['id' => $id, 'message' => 'País creado correctamente.'];
    }

    public function actualizarPais($id, $data)
    {
        if (empty($data['nombre'])) {
            throw new Exception("El nombre del país es obligatorio.", 400);
        }
        $pais = PaisModel::obtenerPorId($id);
        if (!$pais) {
            throw new Exception("El país especificado no existe.", 400);
        }
        PaisModel::actualizar($id, $data['nombre'], $data['codigo_iso'] ?? null);
        return ['message' => 'País actualizado correctamente.'];
    }

    public function eliminarPais($id)
    {
        $pais = PaisModel::obtenerPorId($id);
        if (!$pais) {
            throw new Exception("El país especificado no existe.", 400);
        }
        // Check dependencies (optional but good practice, though DB FK might handle it)
        // For now, let DB FK constraint trigger exception if deps exist.
        PaisModel::eliminar($id);
        return ['message' => 'País eliminado correctamente.'];
    }

    // --- DEPARTAMENTOS ---
    public function listarDepartamentos($paisId = null)
    {
        return DepartamentoModel::listarPorPais($paisId);
    }

    public function crearDepartamento($data)
    {
        if (empty($data['nombre']) || empty($data['id_pais'])) {
            throw new Exception("Nombre e ID de país son obligatorios.", 400);
        }
        if (!PaisModel::obtenerPorId($data['id_pais'])) {
            throw new Exception("El país especificado no existe.", 400);
        }
        $id = DepartamentoModel::crear($data['nombre'], $data['id_pais']);
        return ['id' => $id, 'message' => 'Departamento creado correctamente.'];
    }

    public function actualizarDepartamento($id, $data)
    {
        if (empty($data['nombre']) || empty($data['id_pais'])) {
            throw new Exception("Nombre e ID de país son obligatorios.", 400);
        }
        if (!DepartamentoModel::obtenerPorId($id)) {
            throw new Exception("El departamento especificado no existe.", 400);
        }
        if (!PaisModel::obtenerPorId($data['id_pais'])) {
            throw new Exception("El país especificado no existe.", 400);
        }
        DepartamentoModel::actualizar($id, $data['nombre'], $data['id_pais']);
        return ['message' => 'Departamento actualizado correctamente.'];
    }

    public function eliminarDepartamento($id)
    {
        if (!DepartamentoModel::obtenerPorId($id)) {
            throw new Exception("El departamento especificado no existe.", 400);
        }
        DepartamentoModel::eliminar($id);
        return ['message' => 'Departamento eliminado correctamente.'];
    }

    // --- MUNICIPIOS ---
    public function listarMunicipios($depId = null)
    {
        return MunicipioModel::listarPorDepartamento($depId);
    }

    public function crearMunicipio($data)
    {
        if (empty($data['nombre']) || empty($data['id_departamento'])) {
            throw new Exception("Nombre e ID de departamento son obligatorios.", 400);
        }
        if (!DepartamentoModel::obtenerPorId($data['id_departamento'])) {
            throw new Exception("El departamento especificado no existe.", 400);
        }
        $id = MunicipioModel::crear($data['nombre'], $data['id_departamento']);
        return ['id' => $id, 'message' => 'Municipio creado correctamente.'];
    }

    public function actualizarMunicipio($id, $data)
    {
        if (empty($data['nombre']) || empty($data['id_departamento'])) {
            throw new Exception("Nombre e ID de departamento son obligatorios.", 400);
        }
        if (!MunicipioModel::obtenerPorId($id)) {
            throw new Exception("El municipio especificado no existe.", 400);
        }
        if (!DepartamentoModel::obtenerPorId($data['id_departamento'])) {
            throw new Exception("El departamento especificado no existe.", 400);
        }
        MunicipioModel::actualizar($id, $data['nombre'], $data['id_departamento']);
        return ['message' => 'Municipio actualizado correctamente.'];
    }

    public function eliminarMunicipio($id)
    {
        if (!MunicipioModel::obtenerPorId($id)) {
            throw new Exception("El municipio especificado no existe.", 400);
        }
        MunicipioModel::eliminar($id);
        return ['message' => 'Municipio eliminado correctamente.'];
    }

    // --- BARRIOS ---
    public function listarBarrios($munId = null)
    {
        return BarrioModel::listarPorMunicipio($munId);
    }

    public function crearBarrio($data)
    {
        if (empty($data['nombre']) || empty($data['id_municipio'])) {
            throw new Exception("Nombre e ID de municipio son obligatorios.", 400);
        }
        if (!MunicipioModel::obtenerPorId($data['id_municipio'])) {
            throw new Exception("El municipio especificado no existe.", 400);
        }
        $id = BarrioModel::crear($data['nombre'], $data['id_municipio']);
        return ['id' => $id, 'message' => 'Barrio creado correctamente.'];
    }

    public function actualizarBarrio($id, $data)
    {
        if (empty($data['nombre']) || empty($data['id_municipio'])) {
            throw new Exception("Nombre e ID de municipio son obligatorios.", 400);
        }
        if (!BarrioModel::obtenerPorId($id)) {
            throw new Exception("El barrio especificado no existe.", 400);
        }
        if (!MunicipioModel::obtenerPorId($data['id_municipio'])) {
            throw new Exception("El municipio especificado no existe.", 400);
        }
        BarrioModel::actualizar($id, $data['nombre'], $data['id_municipio']);
        return ['message' => 'Barrio actualizado correctamente.'];
    }

    public function eliminarBarrio($id)
    {
        if (!BarrioModel::obtenerPorId($id)) {
            throw new Exception("El barrio especificado no existe.", 400);
        }
        BarrioModel::eliminar($id);
        return ['message' => 'Barrio eliminado correctamente.'];
    }
}
