# Todo Application - Full Stack CYP Example

model Todo
    id: int
    title: string
    completed: bool
    user_id: int
    user belongsTo User
end

model User
    id: int
    name: string
    email: string
    todos hasMany Todo
end

api GET "/api/todos"
    todos = ["item1", "item2"]
    return todos
end

api POST "/api/todos"
    data = {title: "New Todo"}
    return data
end

page home
    title = "My Todo App"
    say "Title: {title}"
end

task greet(name)
    say "Hello, {name}!"
end

if true
    say "This is true"
else
    say "This is false"
end

for item in [1, 2, 3]
    say "Item: {item}"
end

repeat 5
    say "Counting..."
end

name = "Cypher"
say "Hello, {name}"
