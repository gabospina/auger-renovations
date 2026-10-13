import os

ROOT = r"C:/xampp/htdocs/auger-renovations"

def show_tree(path, prefix=""):
    items = sorted(os.listdir(path))
    for index, item in enumerate(items):
        full_path = os.path.join(path, item)
        connector = "└── " if index == len(items) - 1 else "├── "
        print(prefix + connector + item)
        if os.path.isdir(full_path):
            extension = "    " if index == len(items) - 1 else "│   "
            show_tree(full_path, prefix + extension)

print(f"Project structure for: {ROOT}\n")
show_tree(ROOT)
