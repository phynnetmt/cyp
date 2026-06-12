# Sample CYP Program

name = "Cypher"
version = "0.1.0"

say "Hello from {name} v{version}"

task add(a: int, b: int): int
    return a + b
end

result = add(5, 3)
say "5 + 3 = {result}"

if result > 5
    say "Result is greater than 5"
else
    say "Result is 5 or less"
end

repeat 3
    say "Loop iteration"
end

items = ["apple", "banana", "cherry"]
for item in items
    say "Fruit: {item}"
end
