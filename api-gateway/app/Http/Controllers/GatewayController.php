<?php

namespace App\Http\Controllers;

use App\Helpers\ProxyHelper;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/GatewayController.php — Request Proxy Controller
 *
 * This controller handles every route that needs to be forwarded to a microservice.
 * It contains NO business logic — it only:
 *   1. Builds the correct microservice URL
 *   2. Attaches the authenticated user's identity as headers
 *   3. Calls ProxyHelper to forward the request
 *   4. Returns the microservice's response back to the client
 *
 * The microservices (User, Product, Order) never talk to the browser directly.
 * All traffic goes through this controller.
 */

class GatewayController extends Controller
{
    /*
     * getHeaders() — Build identity headers to forward to microservices.
     *
     * After JwtMiddleware validates a token, it stores the decoded user data
     * on the request as 'jwt_user'. This helper reads that data and builds
     * two headers that microservices use to know who is making the request:
     *
     *   X-User-Id   → the user's numeric ID (e.g. 1 for admin, 2 for john)
     *   X-User-Role → 'admin' or 'user'
     *
     * This way microservices don't need to decode JWT tokens themselves —
     * they just trust the headers the gateway sends them.
     *
     * Returns an empty array for public routes where no token was required.
     */
    private function getHeaders(Request $request): array
    {
        $headers = [];

        // Check if JwtMiddleware stored user data on this request.
        // This will be present on protected routes, absent on public ones.
        if ($request->attributes->has('jwt_user')) {
            $user = $request->attributes->get('jwt_user');

            // 'sub' is the JWT standard claim for "subject" — the user's ID.
            $headers['X-User-Id']   = $user['sub']  ?? '';
            $headers['X-User-Role'] = $user['role'] ?? 'user';
        }

        return $headers;
    }

    // ── User Service Proxy Methods ────────────────────────────────────────────
    //
    // All user routes proxy to: http://localhost:3001/api/users/...
    // All are JWT-protected (see routes/api.php).

    // GET /api/users → User Service GET /api/users
    // Returns a list of all users (passwords excluded by the User Service).
    public function getUsers(Request $request)
    {
        $url    = ProxyHelper::serviceUrl('user_service') . '/api/users';
        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // GET /api/users/{id} → User Service GET /api/users/{id}
    // Returns a single user by their numeric ID.
    public function getUser(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('user_service') . "/api/users/{$id}";
        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // POST /api/users → User Service POST /api/users
    // Creates a new user. $request->all() passes the full request body through.
    public function createUser(Request $request)
    {
        $url    = ProxyHelper::serviceUrl('user_service') . '/api/users';
        $result = ProxyHelper::forward('POST', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // PUT /api/users/{id} → User Service PUT /api/users/{id}
    // Updates an existing user's data (name, email, password).
    public function updateUser(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('user_service') . "/api/users/{$id}";
        $result = ProxyHelper::forward('PUT', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // DELETE /api/users/{id} → User Service DELETE /api/users/{id}
    // Deletes a user. No body needed, so we pass an empty array.
    public function deleteUser(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('user_service') . "/api/users/{$id}";
        $result = ProxyHelper::forward('DELETE', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // ── Product Service Proxy Methods ─────────────────────────────────────────
    //
    // All product routes proxy to: http://localhost:3002/api/products/...
    // GET routes are public; POST/PUT/DELETE require JWT (see routes/api.php).

    // GET /api/products?search=... → Product Service GET /api/products?search=...
    // Lists all products. Optionally filters by name if ?search= is provided.
    // urlencode() prevents special characters in the search term from breaking the URL.
    public function getProducts(Request $request)
    {
        $query = $request->get('search', ''); // read ?search= from the query string
        $url   = ProxyHelper::serviceUrl('product_service') . '/api/products';

        // Only append the search parameter if the client actually sent one.
        if ($query) {
            $url .= '?search=' . urlencode($query);
        }

        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // GET /api/products/{id} → Product Service GET /api/products/{id}
    // Returns a single product by its numeric ID.
    public function getProduct(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('product_service') . "/api/products/{$id}";
        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // POST /api/products → Product Service POST /api/products
    // Creates a new product. Requires JWT (admin use).
    public function createProduct(Request $request)
    {
        $url    = ProxyHelper::serviceUrl('product_service') . '/api/products';
        $result = ProxyHelper::forward('POST', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // PUT /api/products/{id} → Product Service PUT /api/products/{id}
    // Updates an existing product's name, price, description, or stock.
    public function updateProduct(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('product_service') . "/api/products/{$id}";
        $result = ProxyHelper::forward('PUT', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // DELETE /api/products/{id} → Product Service DELETE /api/products/{id}
    // Removes a product from the catalog. No body needed.
    public function deleteProduct(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('product_service') . "/api/products/{$id}";
        $result = ProxyHelper::forward('DELETE', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // ── Order Service Proxy Methods ───────────────────────────────────────────
    //
    // All order routes proxy to: http://localhost:3003/api/orders/...
    // All require JWT. The Order Service uses X-User-Id and X-User-Role headers
    // to decide which orders to return (admins see all, users see only their own).

    // GET /api/orders → Order Service GET /api/orders
    // Returns orders. The Order Service filters by X-User-Id unless role is 'admin'.
    public function getOrders(Request $request)
    {
        $url    = ProxyHelper::serviceUrl('order_service') . '/api/orders';
        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // GET /api/orders/{id} → Order Service GET /api/orders/{id}
    // Returns a single order. The Order Service checks X-User-Id to prevent
    // users from viewing other users' orders.
    public function getOrder(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('order_service') . "/api/orders/{$id}";
        $result = ProxyHelper::forward('GET', $url, [], $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // POST /api/orders → Order Service POST /api/orders
    // Places a new order. The Order Service reads X-User-Id to assign the order
    // to the correct user automatically.
    public function createOrder(Request $request)
    {
        $url    = ProxyHelper::serviceUrl('order_service') . '/api/orders';
        $result = ProxyHelper::forward('POST', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }

    // PUT /api/orders/{id}/status → Order Service PUT /api/orders/{id}/status
    // Updates an order's status (e.g. pending → processing → completed).
    // Expects body: { "status": "processing" }
    public function updateOrderStatus(Request $request, $id)
    {
        $url    = ProxyHelper::serviceUrl('order_service') . "/api/orders/{$id}/status";
        $result = ProxyHelper::forward('PUT', $url, $request->all(), $this->getHeaders($request));
        return response()->json($result['body'], $result['status']);
    }
}
