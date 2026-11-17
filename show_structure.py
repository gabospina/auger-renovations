import os

ROOT = r"C:/xampp/htdocs/auger-renovations"

EXCLUDED = {
    '.git',
    'node_modules',
    'vendor',
    '__pycache__'
}

def show_tree(path, prefix=""):
    items = [
    x for x in sorted(os.listdir(path))
    if x not in EXCLUDED
]
    for index, item in enumerate(items):
        full_path = os.path.join(path, item)
        connector = "└── " if index == len(items) - 1 else "├── "
        print(prefix + connector + item)
        if os.path.isdir(full_path):
            extension = "    " if index == len(items) - 1 else "│   "
            show_tree(full_path, prefix + extension)

print(f"Project structure for: {ROOT}\n")
show_tree(ROOT)
