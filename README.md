# QualityDocD 📄🚀

QualityDocD es una plataforma de gestión de calidad de documentos políglota y lista para producción. Está diseñada para organizaciones que requieren flujos de trabajo de aprobación estructurados, registros de auditoría inmutables y búsquedas rápidas de texto completo, todo coordinado y orquestado mediante **Docker**.

## 🏗️ Arquitectura del Sistema

El proyecto implementa un enfoque **políglota**, asignando cada responsabilidad técnica al stack más adecuado en lugar de forzar una única tecnología:

* **Aplicación .NET MVC (Puerto 5001):** Interfaz de usuario principal. [cite_start]Gestiona el ciclo CRUD de documentos, flujos de aprobación y autenticación por cookies[cite: 6, 12]. [cite_start]Incorpora un proxy inverso **YARP** para unificar el flujo de tráfico hacia los demás servicios[cite: 16].
* [cite_start]**API REST Node.js (Puerto 5000):** API programática en TypeScript (Express 5) que maneja un entorno multiarrendamiento (*multi-tenant*) aislado por empresa mediante tokens JWT[cite: 17, 19, 461].
* [cite_start]**Microservicio de Búsqueda (Puerto 3001):** Servicio ligero en Node.js enfocado exclusivamente en indexar y consultar metadatos en milisegundos[cite: 21, 168].
* [cite_start]**Portal PHP (Puerto 8080):** Panel de control de solo lectura optimizado para visualización de reportes e historial de auditorías de cumplimiento[cite: 8, 178].


### 🗄️ Persistencia de Datos (Polyglot Persistence)
* [cite_start]**SQL Server 2022:** Fuente de la verdad transaccional para documentos, usuarios y estados del flujo de trabajo de la app .NET[cite: 28, 166].
* [cite_start]**PostgreSQL 16:** Almacén de logs de auditoría inmutables y persistencia relacional para la API multiinquilino de Node.js[cite: 30, 167].
* [cite_start]**MongoDB 7:** Indexación de metadatos flexibles para habilitar búsquedas de texto completo ponderadas de alto rendimiento[cite: 31, 168].

---

## ⚡ Capacidades Clave

* [cite_start]**Flujo de Trabajo Estricto:** Los documentos transicionan por estados claros (`Draft` ➡️ `Under Review` ➡️ `Approved` / `Rejected` / `Pending Changes` ➡️ `Obsolete`)[cite: 389].
* [cite_start]**Seguridad RBAC:** Control de acceso basado en roles tanto en la interfaz .NET (`Admin`, `Manager`, `Reviewer`, `Editor`, `Viewer`) como restricciones jerárquicas en la API[cite: 393, 394, 150].
* [cite_start]**Escritura Dual de Auditoría:** Cada cambio de estado se registra simultáneamente en SQL Server y PostgreSQL, garantizando la disponibilidad de los reportes de cumplimiento sin depender de la base de datos principal[cite: 399, 401].
* [cite_start]**Búsqueda Avanzada:** Motor de búsqueda ponderado en MongoDB (los títulos tienen x10 más relevancia que las descripciones) con un mecanismo de *fallback* secundario basado en tokens en PostgreSQL[cite: 217, 298].

---

## 🚀 Inicio Rápido (Local)

### Requisitos Previos
* [cite_start]Docker Desktop / Docker Compose v2 [cite: 47]
* [cite_start]Mínimo 4 GB de RAM disponibles (SQL Server Express requiere al menos 2 GB) [cite: 51]

### Pasos de Configuración

1. **Clonar el repositorio y configurar entorno:**
   ```bash
   git clone [https://github.com/juescoryisus/QualityDocD.git](https://github.com/juescoryisus/QualityDocD.git)
   cd QualityDocD
   cp .env.example .env

   📥 TRÁFICO DEL USUARIO / NAVEGADOR (Puerto 5001)
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                 APLICACIÓN .NET MVC (YARP)                  │
│  - Interfaz de Usuario Principal                            │
│  - Gestión del Ciclo de Vida del Documento                  │
│  - Autenticación por Cookies (Sesión de 8h)                 │
└───────────────┬──────────────┬──────────────┬───────────────┘
                │              │              │
       (YARP Proxy)   (YARP Proxy)            │ (Sincrónico)
  /node-api/*   │      /search/*       │      │
                ▼              ▼              ▼
 ┌──────────────────┐  ┌──────────────┐  ┌────────────────────┐
 │ API REST NODE.JS │  │ SERV. BUSC.  │  │   SQL SERVER 2022  │
 │ (Puerto 5000)    │  │ (Puerto 3001)│  │    (Puerto 1433)   │
 │                  │  │              │  │                    │
 │ - Express 5 / TS │  │ - Node /     │  │ - Fuente de verdad │
 │ - Multi-tenant   │  │   Mongoose   │  │   transaccional    │
 │ - Auth por JWT   │  │ - Indexación │  │ - Tablas: Docs,    │
 └────────┬─────────┘  └──────┬───────┘  │   Users, Approvals │
          │                   │          └────────────────────┘
          │ (Drizzle ORM)     │
          ▼                   │ (Mongoose)
 ┌──────────────────┐         ▼
 │  POSTGRESQL 16   │  ┌──────────────┐
 │  (Puerto 5432)   │  │  MONGODB 7   │◀─── (Sync Asíncrono HTTP)
 │                  │  │(Puerto 27017)│     desde App .NET cuando
 │ - Tablas API     │  │              │     un doc es Aprobado
 │ - Almacén de     │  │- Metadata    │
 │   Auditoría      │  │- Índice de   │
 └────────▲─────────┘  │  Texto Corp. │
          │            └──────▲───────┘
          │                   │
          └─────────┬─────────┘
                    │ (PDO / HTTP)
                    │
       ┌────────────┴────────────┐
       │       PORTAL PHP        │
       │     (Puerto 8080)       │
       │                         │
       │ - Panel de Auditoría    │
       │ - Consultas de Solo Reg.│
       └─────────────────────────┘