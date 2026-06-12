# AI Agent Example
agent Assistant: gpt4
    prompt "You are a helpful assistant"

    task answer(question)
        response = ask(question)
        return response
    end
end

embed php "echo 'Hello from embedded PHP';"

status = 200
result = match status
    200 => "OK"
    404 => "Not Found"
    500 => "Server Error"
end
