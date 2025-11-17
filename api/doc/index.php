<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics API Documentation</title>

    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />

    <style>
        body {
            background-color: #f9fafc;
            font-family: 'Roboto', sans-serif;
        }

        header {
            background: linear-gradient(to right, #007bff, #6610f2);
            color: white;
            padding: 30px 0;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .section-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .code-block {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.95rem;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 15px 0;
            text-align: center;
        }

        .badge-endpoint {
            font-size: 0.85rem;
            color: white;
            background: #6610f2;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <a class="navbar-brand" href="https://cruzvalle.website/">Home</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

        </div>
        <div class="container text-center">
            <h1>Logistics API Documentation</h1>
            <p class="lead">Guía práctica para consumir la API local del sistema de paquetería</p>
            <p>
                <a class="btn btn-light btn-sm" href="./swagger-ui/" target="_blank">Abrir Swagger UI</a>
                <a class="btn btn-outline-primary btn-sm" href="./paqueteria_api.yaml" target="_blank">Ver OpenAPI (YAML)</a>
            </p>
        </div>
        <div>
            
        </div>
    </header>

    <div class="container mt-5">
        <!-- Authentication Section -->
        <div class="section-container">
            <h2 class="section-title">1. Authentication (Login)</h2>
            <p>Authenticate users and generate a JWT token for secure access to the API.</p>
            <h4>Endpoint:</h4>
            <div class="code-block">
                <span class="badge-endpoint">POST</span> http://localhost/paqueteriacz/api/auth/login
            </div>
            <h4>Request Parameters:</h4>
            <ul>
                <li><strong>email</strong> (string): User's email</li>
                <li><strong>password</strong> (string): User's password</li>
            </ul>
            <h4>Example Request Body:</h4>
            <div class="code-block">
                {
                "email": "admin@example.com",
                "password": "123456"
                }
            </div>
            <h4>Example Response:</h4>
            <div class="code-block">
                {
                "success": true,
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                }
            </div>
        </div>

        <!-- Token Usage Section -->
        <div class="section-container">
            <h2 class="section-title">2. Using the JWT Token</h2>
            <p>Include the generated token in the <strong>Authorization</strong> header for all API requests.</p>
            <h4>Header Example:</h4>
            <div class="code-block">
                Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
            </div>
            <p>The API validates the token before processing any request. Ensure the token is valid and not expired.</p>
            <h4>Where the secret is configured</h4>
            <p>The JWT secret key used to sign/verify tokens is defined in the application configuration: <code>config/config.php</code> as the constant <code>JWT_SECRET_KEY</code>. Example:</p>
            <div class="code-block">// config/config.php
define('JWT_SECRET_KEY', 'your_secret_key_here');</div>
            <p><strong>Security note:</strong> Keep this key secret. In production prefer to load it from an environment variable and not commit secrets to source control.</p>
        </div>

        <!-- CRUD Section -->
        <div class="section-container">
            <h2 class="section-title">3. Operations for Orders</h2>

            <!-- Create Order -->
            <h3>3.1 Create an Order</h3>
            <p>Add a new order to the system.</p>
            <h4>Endpoint:</h4>
            <div class="code-block">
                <span class="badge-endpoint">POST</span> http://localhost/paqueteriacz/api/pedidos/crear
            </div>
            <h4>Headers:</h4>
            <div class="code-block">
                Authorization: Bearer &lt;TOKEN_OBTENIDO_EN_LOGIN&gt;
            </div>
            <h4>Example Response:</h4>
            <div class="code-block">
                {
                "numero_orden": 14522001,
                "destinatario": "Carlos Perez",
                "telefono": "50588889999",
                "precio": 2500,
                "producto": "Green Coffee - 3",
                "cantidad": 3,
                "pais": "Nicaragua",
                "departamento": "León",
                "municipio": "León",
                "barrio": "San Felipe",
                "direccion": "De la Catedral 2 cuadras al norte.",
                "zona": "Centro",
                "comentario": "Entrega urgente",
                "coordenadas": "12.437532,-86.879175"
                }
            </div>
                        <h4>Example Response:</h4>
                        <div class="code-block">
                                {
                                "success": true,
                                "message": "Pedido creado correctamente.",
                                "data": 15
                                }
                        </div>

                        <h4>cURL ejemplo (Create):</h4>
                        <div class="code-block">
curl -X POST "http://localhost/paqueteriacz/api/pedidos/crear" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer <TOKEN>" \
    -d '{
        "numero_orden": 90001,
        "destinatario": "Cliente Prueba",
        "telefono": "0999999999",
        "producto": "Producto X",
        "cantidad": 1,
        "coordenadas": "-0.180653,-78.467838",
        "direccion": "Calle Falsa 123",
        "pais": "EC",
        "departamento": "Pichincha",
        "municipio": "Quito"
    }'
                        </div>

                        <h4>cURL ejemplo (Listar):</h4>
                        <div class="code-block">
curl "http://localhost/paqueteriacz/api/pedidos/listar"
                        </div>

                        <h4>Postman</h4>
                        <p>Importa la colección y el environment incluidos en el repositorio:</p>
                        <ul>
                                <li><strong>postman/Paqueteria_API.postman_collection.json</strong></li>
                                <li><strong>postman/Paqueteria_Local.postman_environment.json</strong></li>
                        </ul>


        </div>
        <!-- Products Section -->
        <div class="section-container">
            <h2 class="section-title">4. Products (CRUD)</h2>
            <p>Endpoints to manage products. All endpoints that change data require a valid JWT in the <code>Authorization</code> header.</p>
            <h4>Endpoints (examples):</h4>
            <div class="code-block">
                <strong>List:</strong> GET http://localhost/paqueteriacz/api/productos/listar
            </div>
            <div class="code-block">
                <strong>Create:</strong> POST http://localhost/paqueteriacz/api/productos/crear
            </div>
            <div class="code-block">
                <strong>View:</strong> GET http://localhost/paqueteriacz/api/productos/ver/{id}
            </div>
            <div class="code-block">
                <strong>Update:</strong> POST http://localhost/paqueteriacz/api/productos/actualizar/{id}
            </div>
            <div class="code-block">
                <strong>Delete:</strong> POST http://localhost/paqueteriacz/api/productos/eliminar/{id}
            </div>

            <h4>Example create body:</h4>
            <div class="code-block">
                {
                "nombre": "Producto X",
                "descripcion": "Descripción opcional",
                "precio_usd": 9.5
                }
            </div>

            <h4>Example create response:</h4>
            <div class="code-block">
                {
                "success": true,
                "message": "Producto creado correctamente.",
                "id": 42
                }
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Logistics API - All Rights Reserved</p>
    </footer>
    <!-- Bootstrap JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
        integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+"
        crossorigin="anonymous"></script>
</body>

</html>