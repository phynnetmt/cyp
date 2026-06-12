# Full-Stack Application Example
# Generates: React frontend + Laravel backend + PostgreSQL database

model User
    id: int
    name: string
    email: string unique
    password: string
end

model Product
    id: int
    name: string
    price: float
    description: text
    user_id: int
    user belongsTo User
end

page home
    title = "Welcome to CYP"
    say "<h1>{title}</h1>"
    say "<p>Built with Cypher Language</p>"
end

page dashboard
    title = "Admin Dashboard"
    items = ["Users", "Products", "Settings"]
    for item in items
        say "<div>{item}</div>"
    end
end

api GET "/api/products"
    return Product::all()
end

api POST "/api/products"
    return {status: "created"}
end

api GET "/api/users"
    return User::all()
end

task sendWelcomeEmail(user)
    say "Sending welcome email to {user}"
end
