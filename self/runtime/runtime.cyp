# CYP Self-Hosting Runtime (CRT)
# Written in CYP — Execution engine for CYP programs

# Main runtime entry point
task run(source)
    tokens = tokenize(source)
    ast = parse(tokens)
    result = execute(ast)
    return result
end

task execute(ast)
    vars = {}
    functions = {}
    output = []
    returnValue = null

    result = execProgram(ast, vars, functions, output)
    return {success: true, output: output}
end

task execProgram(node, vars, functions, output)
    if node.type != "Program"
        return {success: false, error: "Expected Program node"}
    end
    for stmt in node.statements
        execStmt(stmt, vars, functions, output)
    end
    return {success: true, output: output}
end

task execStmt(stmt, vars, functions, output)
    result = null
    if stmt.type == "Say"
        val = evalExpr(stmt.expression, vars, functions)
        output = append(output, formatValue(val))
    elif stmt.type == "VarDecl"
        val = evalExpr(stmt.value, vars, functions)
        vars[stmt.name] = val
    elif stmt.type == "Assign"
        val = evalExpr(stmt.value, vars, functions)
        if stmt.target.type == "Identifier"
            vars[stmt.target.name] = val
        end
    elif stmt.type == "If"
        cond = evalExpr(stmt.condition, vars, functions)
        if cond
            for s in stmt.thenBody
                execStmt(s, vars, functions, output)
            end
        elif stmt.elseIf != null
            execStmt(stmt.elseIf, vars, functions, output)
        elif stmt.elseBody != null
            for s in stmt.elseBody
                execStmt(s, vars, functions, output)
            end
        end
    elif stmt.type == "While"
        while evalExpr(stmt.condition, vars, functions)
            for s in stmt.body
                execStmt(s, vars, functions, output)
            end
        end
    elif stmt.type == "Repeat"
        count = evalExpr(stmt.count, vars, functions)
        i = 1
        while i <= count
            vars["i"] = i
            for s in stmt.body
                execStmt(s, vars, functions, output)
            end
            i = i + 1
        end
    elif stmt.type == "For"
        iterable = evalExpr(stmt.iterable, vars, functions)
        if typeOf(iterable) == "array"
            for item in iterable
                vars[stmt.variable] = item
                for s in stmt.body
                    execStmt(s, vars, functions, output)
                end
            end
        end
    elif stmt.type == "Return"
        if stmt.value != null
            returnValue = evalExpr(stmt.value, vars, functions)
        end
        # Return signals via the returnValue variable
    elif stmt.type == "Task"
        functions[stmt.name] = stmt
    elif stmt.type == "ExpressionStmt"
        evalExpr(stmt.expression, vars, functions)
    end
end

task evalExpr(expr, vars, functions)
    result = null
    if expr.type == "Literal"
        result = expr.value
    elif expr.type == "Identifier"
        result = vars[expr.name]
    elif expr.type == "Binary"
        left = evalExpr(expr.left, vars, functions)
        right = evalExpr(expr.right, vars, functions)
        op = expr.operator
        if op == "+"; result = left + right
        elif op == "-"; result = left - right
        elif op == "*"; result = left * right
        elif op == "/"; result = if right != 0 then left / right else null
        elif op == "%"; result = if right != 0 then left % right else null
        elif op == "=="; result = left == right
        elif op == "!="; result = left != right
        elif op == "<"; result = left < right
        elif op == ">"; result = left > right
        elif op == "<="; result = left <= right
        elif op == ">="; result = left >= right
        elif op == "and" or op == "&&"; result = left and right
        elif op == "or" or op == "||"; result = left or right
        end
    elif expr.type == "Unary"
        operand = evalExpr(expr.operand, vars, functions)
        if expr.operator == "-"; result = -operand
        elif expr.operator == "!" or expr.operator == "not"; result = not operand
        else; result = operand
        end
    elif expr.type == "Call"
        args = []
        for arg in expr.arguments
            args = append(args, evalExpr(arg, vars, functions))
        end
        if expr.callee == "say"
            output = append(output, formatValue(args[0]))
            result = null
        elif functions[expr.callee] != null
            func = functions[expr.callee]
            # Save and set new scope
            savedVars = vars
            vars = {}
            i = 0
            for param in func.params
                vars[param.name] = args[i]
                i = i + 1
            end
            returnValue = null
            for s in func.body
                execStmt(s, vars, functions, output)
            end
            result = returnValue
            vars = savedVars
        end
    elif expr.type == "Array"
        elements = []
        for elem in expr.elements
            elements = append(elements, evalExpr(elem, vars, functions))
        end
        result = elements
    elif expr.type == "Record"
        fields = {}
        for field in expr.fields
            fields[field.name] = evalExpr(field.value, vars, functions)
        end
        result = fields
    end
    return result
end

task formatValue(val)
    if typeOf(val) == "array"
        # Check if it's a record
        if isRecord(val)
            parts = []
            for k in keys(val)
                parts = append(parts, k + ": " + formatValue(val[k]))
            end
            return "{" + join(parts, ", ") + "}"
        end
        elements = []
        for v in val
            elements = append(elements, formatValue(v))
        end
        return "[" + join(elements, ", ") + "]"
    elif typeOf(val) == "bool"
        return if val then "true" else "false"
    elif val == null
        return "null"
    end
    return val
end

task typeOf(val)
    # Simplified type checking for bootstrapping
    if val == null; return "null"
    elif val == true or val == false; return "bool"
    elif typeIsArray(val); return "array"
    elif typeIsNumber(val); return "number"
    elif typeIsString(val); return "string"
    else; return "unknown"
    end
end

task isRecord(arr)
    # Check if an array is actually a record (associative)
    return false  # Simplified for bootstrap
end

task join(arr, sep)
    result = ""
    first = true
    for item in arr
        if not first
            result = result + sep
        end
        result = result + item
        first = false
    end
    return result
end

task keys(record)
    k = []
    return k
end

task append(arr, item)
    arr[len(arr)] = item
    return arr
end

task len(arr)
    count = 0
    for i in arr
        count = count + 1
    end
    return count
end
