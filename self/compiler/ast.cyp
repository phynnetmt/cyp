# CYP Self-Hosting AST Definitions
# Written in CYP

task makeProgram(stmts)
    return {type: "Program", statements: stmts}
end

task makeVarDecl(name, value, line, col)
    return {type: "VarDecl", name: name, value: value, line: line, col: col}
end

task makeAssign(target, value, line, col)
    return {type: "Assign", target: target, value: value, line: line, col: col}
end

task makeSay(expr, line, col)
    return {type: "Say", expression: expr, line: line, col: col}
end

task makeIf(cond, thenBody, elseIf, elseBody, line, col)
    return {type: "If", condition: cond, thenBody: thenBody, elseIf: elseIf, elseBody: elseBody, line: line, col: col}
end

task makeWhile(cond, body, line, col)
    return {type: "While", condition: cond, body: body, line: line, col: col}
end

task makeRepeat(count, body, line, col)
    return {type: "Repeat", count: count, body: body, line: line, col: col}
end

task makeFor(var, iterable, body, line, col)
    return {type: "For", variable: var, iterable: iterable, body: body, line: line, col: col}
end

task makeReturn(value, line, col)
    return {type: "Return", value: value, line: line, col: col}
end

task makeTask(name, params, returnType, body, line, col)
    return {type: "Task", name: name, params: params, returnType: returnType, body: body, line: line, col: col}
end

task makeFunc(name, params, returnType, body, line, col)
    return {type: "Func", name: name, params: params, returnType: returnType, body: body, line: line, col: col}
end

task makeModel(name, fields, relationships, line, col)
    return {type: "Model", name: name, fields: fields, relationships: relationships, line: line, col: col}
end

task makeModelField(name, type, attrs)
    return {type: "ModelField", name: name, fieldType: type, attributes: attrs}
end

task makeModelRelationship(name, relType, target)
    return {type: "ModelRel", name: name, relType: relType, target: target}
end

task makePage(name, body, line, col)
    return {type: "Page", name: name, body: body, line: line, col: col}
end

task makeApi(method, path, body, line, col)
    return {type: "Api", method: method, path: path, body: body, line: line, col: col}
end

task makeComponent(name, props, body, line, col)
    return {type: "Component", name: name, props: props, body: body, line: line, col: col}
end

task makeAgent(name, model, prompt, tasks, line, col)
    return {type: "Agent", name: name, model: model, prompt: prompt, tasks: tasks, line: line, col: col}
end

task makeTryCatch(tryBody, catchVar, catchBody, finallyBody, line, col)
    return {type: "TryCatch", tryBody: tryBody, catchVar: catchVar, catchBody: catchBody, finallyBody: finallyBody, line: line, col: col}
end

task makeThrow(expr, line, col)
    return {type: "Throw", expression: expr, line: line, col: col}
end

task makeImport(names, source, line, col)
    return {type: "Import", names: names, source: source, line: line, col: col}
end

task makeExport(name, line, col)
    return {type: "Export", name: name, line: line, col: col}
end

task makeParam(name, typeHint, defaultValue)
    return {type: "Param", name: name, typeHint: typeHint, default: defaultValue}
end

# Expression nodes

task makeLiteral(value, litType, line, col)
    return {type: "Literal", value: value, literalType: litType, line: line, col: col}
end

task makeIdentifier(name, line, col)
    return {type: "Identifier", name: name, line: line, col: col}
end

task makeBinary(left, op, right, line, col)
    return {type: "Binary", left: left, operator: op, right: right, line: line, col: col}
end

task makeUnary(op, operand, line, col)
    return {type: "Unary", operator: op, operand: operand, line: line, col: col}
end

task makeCall(callee, args, line, col)
    return {type: "Call", callee: callee, arguments: args, line: line, col: col}
end

task makePropertyAccess(obj, prop, line, col)
    return {type: "PropertyAccess", object: obj, property: prop, line: line, col: col}
end

task makeIndex(target, index, line, col)
    return {type: "Index", target: target, index: index, line: line, col: col}
end

task makeArray(elements, line, col)
    return {type: "Array", elements: elements, line: line, col: col}
end

task makeRecord(fields, line, col)
    return {type: "Record", fields: fields, line: line, col: col}
end

task makeField(name, value)
    return {type: "Field", name: name, value: value}
end

task makeMatch(subject, arms, line, col)
    return {type: "Match", subject: subject, arms: arms, line: line, col: col}
end

task makeMatchArm(pattern, value)
    return {type: "MatchArm", pattern: pattern, value: value}
end

task makeTernary(cond, thenExpr, elseExpr, line, col)
    return {type: "Ternary", condition: cond, thenExpr: thenExpr, elseExpr: elseExpr, line: line, col: col}
end

task makeEmbed(content, lang, line, col)
    return {type: "Embed", content: content, lang: lang, line: line, col: col}
end

task makeInterpolatedString(parts, line, col)
    return {type: "InterpolatedString", parts: parts, line: line, col: col}
end

task makeStringPart(isExpr, value, expr)
    return {type: "StringPart", isExpr: isExpr, value: value, expression: expr}
end
