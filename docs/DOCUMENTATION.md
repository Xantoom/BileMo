# BileMo API Documentation

Welcome to the BileMo API documentation. This API allows you to manage products and users associated with customers.

## Base URL

All API endpoints are relative to the base URL: `/api`

## Authentication

Authentication is required for accessing most endpoints. Please include your JWT token in the `Authorization` header as a Bearer token:

```
Authorization: Bearer <YOUR_JWT_TOKEN>
```

*(Note: Authentication implementation details are not shown in the provided controllers but assumed for a production API).*

## Caching

Responses are cached to improve performance. Standard HTTP caching headers (`ETag`, `Cache-Control`) are used. Clients are encouraged to respect these headers.

## Resources

### Products

#### List Products

Retrieves a paginated list of available products.

*   **URL:** `/products`
*   **Method:** `GET`
*   **Query Parameters:**
    *   `page` (integer, optional, default: 1): The page number to retrieve.
    *   `limit` (integer, optional, default: 10): The number of items per page.
*   **Success Response:**
    *   **Code:** `200 OK`
    *   **Content:**
        ```json
        {
          "data": [
            {
              "id": 1,
              "name": "Example Phone Model X",
              "brand": "Brand A",
              "price": "699.99",
              "description": "Latest model with advanced features."
              // ... other product properties
            },
            {
              "id": 2,
              "name": "Example Phone Model Y",
              "brand": "Brand B",
              "price": "499.99",
              "description": "Affordable model with great battery life."
              // ... other product properties
            }
            // ... more products
          ],
          "meta": {
            "current_page": 1,
            "per_page": 10,
            "total_items": 50,
            "total_pages": 5
          },
          "_links": {
            "self": "http://<your-domain>/api/products?page=1&limit=10",
            "first": "http://<your-domain>/api/products?page=1&limit=10",
            "last": "http://<your-domain>/api/products?page=5&limit=10",
            "next": "http://<your-domain>/api/products?page=2&limit=10"
            // "previous" link appears on page > 1
          }
        }
        ```
*   **Error Response:**
    *   *(Specific errors depend on implementation, e.g., authentication failure)*

---

#### Get Product Details

Retrieves the details of a specific product by its ID.

*   **URL:** `/products/{id}`
*   **Method:** `GET`
*   **URL Parameters:**
    *   `id` (integer, required): The unique identifier of the product.
*   **Success Response:**
    *   **Code:** `200 OK`
    *   **Content:**
        ```json
        {
          "data": {
            "id": 1,
            "name": "Example Phone Model X",
            "brand": "Brand A",
            "price": "699.99",
            "description": "Latest model with advanced features."
            // ... other product properties
          },
          "_links": {
            "self": "http://<your-domain>/api/products/1",
            "collection": "http://<your-domain>/api/products"
          }
        }
        ```
*   **Error Response:**
    *   **Code:** `404 Not Found`
    *   **Content:**
        ```json
        {
          "error": "Product not found"
        }
        ```

---

### Customers / Users

#### List Users for a Customer

Retrieves a paginated list of users linked to a specific customer.

*   **URL:** `/customers/{id}/users`
*   **Method:** `GET`
*   **URL Parameters:**
    *   `id` (integer, required): The unique identifier of the customer.
*   **Query Parameters:**
    *   `page` (integer, optional, default: 1): The page number to retrieve.
    *   `limit` (integer, optional, default: 10): The number of items per page.
*   **Success Response:**
    *   **Code:** `200 OK`
    *   **Content:**
        ```json
        {
          "data": [
            {
              "id": 101,
              "username": "user_a",
              "email": "user_a@example.com",
              "firstName": "John",
              "lastName": "Doe"
              // ... other user properties (excluding sensitive data like password)
            },
            {
              "id": 102,
              "username": "user_b",
              "email": "user_b@example.com",
              "firstName": "Jane",
              "lastName": "Smith"
              // ... other user properties
            }
            // ... more users
          ],
          "meta": {
            "current_page": 1,
            "per_page": 10,
            "total_items": 25,
            "total_pages": 3
          },
          "_links": {
            "self": "http://<your-domain>/api/customers/5/users?page=1&limit=10",
            "first": "http://<your-domain>/api/customers/5/users?page=1&limit=10",
            "last": "http://<your-domain>/api/customers/5/users?page=3&limit=10",
            "next": "http://<your-domain>/api/customers/5/users?page=2&limit=10",
            "customer": "http://<your-domain>/api/customers/5" // Link to the customer resource (adjust if customer endpoint exists)
            // "previous" link appears on page > 1
          }
        }
        ```
*   **Error Response:**
    *   **Code:** `404 Not Found`
    *   **Content:**
        ```json
        {
          "error": "Customer not found"
        }
        ```

---
