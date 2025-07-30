<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Events Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #6772e5;
            text-align: center;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 150px;
            font-family: monospace;
        }
        button {
            background-color: #6772e5;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #5469d4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .error {
            color: #d00;
            margin-top: 10px;
        }
        .success {
            color: #090;
            margin-top: 10px;
        }
        .json-viewer {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f8f8f8;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stripe Events Manager</h1>
        
        <div class="section">
            <h2>Database Connection</h2>
            <div class="form-group">
                <label for="dbHost">Host:</label>
                <input type="text" id="dbHost" value="dpg-d257e563jp1c73e216h0-a.oregon-postgres.render.com">
            </div>
            <div class="form-group">
                <label for="dbPort">Port:</label>
                <input type="text" id="dbPort" value="5432">
            </div>
            <div class="form-group">
                <label for="dbName">Database:</label>
                <input type="text" id="dbName" value="dbstripe_ul7f">
            </div>
            <div class="form-group">
                <label for="dbUser">Username:</label>
                <input type="text" id="dbUser" value="dbstripe_ul7f_user">
            </div>
            <div class="form-group">
                <label for="dbPassword">Password:</label>
                <input type="password" id="dbPassword" value="j7rP4lHTCdjmlVNIRouEhlJLiX8LiZue">
            </div>
            <button id="testConnection">Test Connection</button>
            <div id="connectionStatus"></div>
        </div>
        
        <div class="section">
            <h2>Add New Event</h2>
            <div class="form-group">
                <label for="eventId">Event ID:</label>
                <input type="text" id="eventId" placeholder="evt_1...">
            </div>
            <div class="form-group">
                <label for="eventType">Event Type:</label>
                <input type="text" id="eventType" placeholder="payment_intent.succeeded">
            </div>
            <div class="form-group">
                <label for="eventPayload">Payload (JSON):</label>
                <textarea id="eventPayload" placeholder='{
    "id": "evt_1...",
    "object": "event",
    "api_version": "2023-08-16",
    "created": 1234567890,
    "data": {
        "object": {
            "id": "pi_1...",
            "object": "payment_intent",
            "amount": 1000,
            "currency": "usd"
        }
    }
}'></textarea>
            </div>
            <button id="addEvent">Add Event</button>
            <div id="addEventStatus"></div>
        </div>
        
        <div class="section">
            <h2>Event List</h2>
            <button id="refreshEvents">Refresh Events</button>
            <div id="eventsTable"></div>
        </div>
    </div>

    <script>
        // Database configuration
        const config = {
            host: document.getElementById('dbHost').value,
            port: document.getElementById('dbPort').value,
            database: document.getElementById('dbName').value,
            user: document.getElementById('dbUser').value,
            password: document.getElementById('dbPassword').value,
            ssl: true
        };

        // Test database connection
        document.getElementById('testConnection').addEventListener('click', async () => {
            const statusElement = document.getElementById('connectionStatus');
            statusElement.textContent = "Testing connection...";
            statusElement.className = "";
            
            try {
                const response = await fetch('https://postgres-proxy-server.onrender.com/test-connection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusElement.textContent = "✅ Connection successful!";
                    statusElement.className = "success";
                } else {
                    statusElement.textContent = `❌ Connection failed: ${result.error}`;
                    statusElement.className = "error";
                }
            } catch (error) {
                statusElement.textContent = `❌ Error: ${error.message}`;
                statusElement.className = "error";
            }
        });

        // Add new event
        document.getElementById('addEvent').addEventListener('click', async () => {
            const statusElement = document.getElementById('addEventStatus');
            statusElement.textContent = "Adding event...";
            statusElement.className = "";
            
            const eventId = document.getElementById('eventId').value.trim();
            const eventType = document.getElementById('eventType').value.trim();
            const eventPayload = document.getElementById('eventPayload').value.trim();
            
            if (!eventId || !eventType || !eventPayload) {
                statusElement.textContent = "❌ Please fill in all fields";
                statusElement.className = "error";
                return;
            }
            
            try {
                // Validate JSON
                const payloadJson = JSON.parse(eventPayload);
                
                const response = await fetch('https://postgres-proxy-server.onrender.com/add-event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ...config,
                        eventId,
                        eventType,
                        payload: payloadJson
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusElement.textContent = "✅ Event added successfully!";
                    statusElement.className = "success";
                    // Clear form
                    document.getElementById('eventId').value = '';
                    document.getElementById('eventType').value = '';
                    document.getElementById('eventPayload').value = '';
                    // Refresh events list
                    loadEvents();
                } else {
                    statusElement.textContent = `❌ Error adding event: ${result.error}`;
                    statusElement.className = "error";
                }
            } catch (error) {
                statusElement.textContent = `❌ Error: ${error.message}`;
                statusElement.className = "error";
            }
        });

        // Load and display events
        async function loadEvents() {
            const eventsTable = document.getElementById('eventsTable');
            eventsTable.innerHTML = "<p>Loading events...</p>";
            
            try {
                const response = await fetch('https://postgres-proxy-server.onrender.com/get-events', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.data.length === 0) {
                        eventsTable.innerHTML = "<p>No events found in the database.</p>";
                        return;
                    }
                    
                    let tableHTML = `
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event ID</th>
                                    <th>Event Type</th>
                                    <th>Received At</th>
                                    <th>Payload</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    result.data.forEach(event => {
                        tableHTML += `
                            <tr>
                                <td>${event.id}</td>
                                <td>${event.event_id}</td>
                                <td>${event.event_type}</td>
                                <td>${new Date(event.received_at).toLocaleString()}</td>
                                <td><div class="json-viewer">${JSON.stringify(event.payload, null, 2)}</div></td>
                            </tr>
                        `;
                    });
                    
                    tableHTML += `
                            </tbody>
                        </table>
                    `;
                    
                    eventsTable.innerHTML = tableHTML;
                } else {
                    eventsTable.innerHTML = `<p class="error">Error loading events: ${result.error}</p>`;
                }
            } catch (error) {
                eventsTable.innerHTML = `<p class="error">Error: ${error.message}</p>`;
            }
        }

        // Refresh events button
        document.getElementById('refreshEvents').addEventListener('click', loadEvents);
        
        // Load events on page load
        document.addEventListener('DOMContentLoaded', loadEvents);
    </script>
</body>
</html>
