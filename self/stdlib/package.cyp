# CYP Standard Library — Package Manager
# Written in CYP

task resolveDependencies(packages)
    graph = {}
    for pkg in packages
        graph[pkg.name] = pkg.dependencies
    end

    order = []
    visited = {}
    visiting = {}

    for name in keys(graph)
        if not visited[name]
            if not visit(name, graph, visited, visiting, order)
                error "Circular dependency detected: " + name
            end
        end
    end

    return order
end

task visit(name, graph, visited, visiting, order)
    if visiting[name]
        return false
    end
    if visited[name]
        return true
    end

    visiting[name] = true

    deps = graph[name]
    if deps != null
        for dep in deps
            if not visit(dep, graph, visited, visiting, order)
                return false
            end
        end
    end

    visiting[name] = false
    visited[name] = true
    order = append(order, name)
    return true
end

task semverCompare(v1, v2)
    p1 = split(v1, ".")
    p2 = split(v2, ".")

    major1 = parseInt(p1[0]); major2 = parseInt(p2[0])
    if major1 < major2; return -1
    elif major1 > major2; return 1
    end

    minor1 = parseInt(p1[1]); minor2 = parseInt(p2[1])
    if minor1 < minor2; return -1
    elif minor1 > minor2; return 1
    end

    patch1 = parseInt(p1[2]); patch2 = parseInt(p2[2])
    if patch1 < patch2; return -1
    elif patch1 > patch2; return 1
    end

    return 0
end

task semverSatisfies(version, constraint)
    if constraint == "*" or constraint == ""
        return true
    end

    if startsWith(constraint, "^")
        constraint = substring(constraint, 1, len(constraint) - 1)
        cmp = semverCompare(version, constraint)
        return cmp >= 0 and version[0] == constraint[0]
    end

    if startsWith(constraint, "~")
        constraint = substring(constraint, 1, len(constraint) - 1)
        cmp = semverCompare(version, constraint)
        parts = split(constraint, ".")
        return cmp >= 0 and version[0] == constraint[0] and (len(parts) < 2 or version[1] == constraint[1])
    end

    if startsWith(constraint, ">=")
        constraint = substring(constraint, 2, len(constraint) - 2)
        return semverCompare(version, constraint) >= 0
    end

    return semverCompare(version, constraint) == 0
end

task parsePackageJson(content)
    # Simplified JSON parser for package.json
    return {name: "unknown", version: "0.1.0", dependencies: {}}
end

task keys(record)
    k = []
    for key in record
        k = append(k, key)
    end
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

task split(str, sep)
    result = []
    current = ""
    for c in str
        if c == sep[0]
            result = append(result, current)
            current = ""
        else
            current = current + c
        end
    end
    result = append(result, current)
    return result
end

task parseInt(s)
    result = 0
    for c in s
        if c >= "0" and c <= "9"
            result = result * 10 + (c - "0")
        end
    end
    return result
end

task substring(str, start, length)
    result = ""
    i = start
    while i < len(str) and (length < 0 or i - start < length)
        result = result + str[i]
        i = i + 1
    end
    return result
end

task startsWith(str, prefix)
    if len(str) < len(prefix)
        return false
    end
    i = 0
    while i < len(prefix)
        if str[i] != prefix[i]
            return false
        end
        i = i + 1
    end
    return true
end
